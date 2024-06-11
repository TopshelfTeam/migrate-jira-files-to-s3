<?php

/* *** *** *** *** *** *** *** *** *** *** *** *** 
 * Description:
 *   This script connects to a Jira Cloud instance, goes through the desired projects (as configured by $projectKeysFilter),
 *   and finds any issues that have attachments. It then downloads the attachments locally, into a folder ($BASE_PATH) using a 
 *   folder structure that Simple Cloudfiles understands.
 *   We then take the downloaded file, and upload it to S3, into the desired bucket, under the corresponding project & ticket key,
 *   and delete the source file from Jira Cloud.
 *
 * Notes:
 *   - By default, deleting of files from Jira Cloud is disabled, as we do not want this script to be destructive. 
 *     If you want to delete the files from Jira Cloud after they have been uploaded to S3, uncomment the call to deleteFileInJira() (line 143). 
 *     This will then delete the file from Jira after it has been uploaded to S3.
 *
 *   - If you do not wish for this script to upload files directly to S3, then comment out the entire "uploadToS3" section (if + else. Line 141-148).
 *     In this case, the script will simply download files locally in $BASE_PATH, and you can then upload them to S3 yourself, 
 *     using any tool of your choice. 
 *     - Note that the script performs validation and expects S3 related settings to be filled in. If you go this path, just put dummy values.
 *   
 *   - You need to fill in the required values in the `config.php` file.
 *     - details about your Jira Cloud instance + Atlassian credentials. This means, the email you use to login, as well as an API key.
 *       You can obtain an API key by visiting Manage Account -> Security -> Create and Manage API Tokens
 *
 *     - details about your S3 bucket + AWS credentials (key + secret key)
 * 
 *     - which projects you want to export attachments for (default is all projects. But you can limit it to a specific set of project keys)
 *       note: the settings contain a JQL query used to find issues with attachments. If you want to further limit issues (beyond just project key),
 *             you can change the JQL query to add more conditions.
 *
 * Prerequisites
 *   - you can run this script in a docker container by using the provided `build.sh` file (to build the image) and `run.sh` (to run the container)
 *   - if you want to run it locally, without Docker, then you will need PHP CLI, as well as the AWS PHP SDK, which can be found here:
 *     https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html
 *     Follow the steps for "Installing by Using the ZIP file" (download and unzip into the same diretory as this script). 
 *     You should then have a "aws" folder.
 *     IMPORTANT NOTE:  if you go this path, update the `require '/aws-sdk-php/vendor/autoload.php';` line in this file and the helpers.php file
 *                      to point to the downloaded version.
 *
 *** *** *** *** *** *** *** *** *** *** *** *** */

// --- --- --- --- --- --- --- --- --- --- --- --- 
// SETUP / DEPENDENCIES
// --- --- --- --- --- --- --- --- --- --- --- --- 

// require 'aws/aws-autoloader.php';
require '/aws-sdk-php/vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;


// include our config with all the settings
require_once 'config.php';

// include our helper functions
require_once 'helpers.php';

// set a timezone so PHP doesn't gag.
date_default_timezone_set('UTC');


// --- --- --- --- --- --- --- --- --- --- --- --- 
// VALIDATION
// make sure we have everything we need
// --- --- --- --- --- --- --- --- --- --- --- --- 
if (empty($ATLASSIAN_USERNAME))  { die("usename is missing!"); }
if (empty($ATLASSIAN_API_KEY))  { die("api key is missing!"); }
if (empty($JIRA_BASE_URL))  { die("JIRA url is missing"); }
if (empty($LOCAL_DOWNLOAD_PATH)) { die("storage path is missing"); }
if (empty($S3_BUCKET_NAME)) { die("S3 bucket name path is missing"); }
if (empty($S3_BUCKET_REGION)) { die("S3 region is missing"); }
if (empty($AWS_ACCESS_KEY)) { die("AWS Key is missing"); }
if (empty($AWS_SECRET_KEY)) { die("AWS Secret Key is missing"); }

// ensure the local download path exists
if (!is_dir($LOCAL_DOWNLOAD_PATH)) { die("The folder $LOCAL_DOWNLOAD_PATH doesn't exist."); }

// return;



// --- --- --- --- --- --- --- --- --- --- --- --- 
// CONFIGURATION / SETUP / CONSTANTS
// --- --- --- --- --- --- --- --- --- --- --- --- 

// create the atlassian auth token, which is used for the actual requests to Jira Cloud
$ATLASSIAN_AUTH_TOKEN = base64_encode("$ATLASSIAN_USERNAME:$ATLASSIAN_API_KEY");


// the S3 client for uploading. Edit the credentials as needed.
$S3_CLIENT = new S3Client([
    'version' => '2006-03-01',
    // the region your bucket is in
    'region' => "$S3_BUCKET_REGION",
    // IAM credentials for accessing the bucket
    'credentials' => [
        'key'    => "$AWS_ACCESS_KEY",
        'secret' => "$AWS_SECRET_KEY"
    ]
]);

// construct the url to the Jira REST Api
$REST_URL = "$JIRA_BASE_URL/rest/api/3";


// --- --- --- --- --- --- --- --- --- --- --- --- 
// SCRIPT LOGIC
// --- --- --- --- --- --- --- --- --- --- --- --- 

// retrieve ALL the projects from Jira
// Note:  change $projectKeysFilter in config.php to change which projects should be processed
$projects = getProjects( $projectKeysFilter );

msg("Number of Projects to process: " . count($projects) . "\n");

// loop through all the projects
for ($p=0; $p < count($projects); $p++) {
    $project = $projects[$p];
    
    msg("Starting Project: $project->key");
    
    // retrieve the tickets that have attachments
    $tickets = getTickets($project->key);
    msg(" - Project: $project->key has " .count($tickets). " tickets with Attachments");

    // go through the tickets, and download the attachments
    for ($t=0; $t < count($tickets); $t++) {
        $ticket = $tickets[$t];
        // msg("   - Ticket: $ticket->key has ".count($ticket->fields->attachment)." attachments - Attachments downloaded: ");
        msg(" - Ticket: $ticket->key has ".count($ticket->fields->attachment)." attachment(s)");
        
        // download the attachment
        for ($a=0; $a < count($ticket->fields->attachment); $a++) {
            $attachment = $ticket->fields->attachment[$a];
            
            //DEBUG:
            // echo "  Attachment ID: $attachment->id \n";
            msg("   - Processing Attachment: $attachment->filename");
            // echo "  Attachment Size: $attachment->size  \n";
            
            // download the file
            downloadFile($project->key, $ticket->key, $attachment->filename, $attachment->content);
            
            // verify the local file
            if ( verifyLocalFile($project->key, $ticket->key, $attachment->filename, $attachment->size) ) {
                // echo "  - Attachment $attachment->filename matches size ($attachment->size) between local copy and Jira Cloud. \n";
                
                // upload file to S3 if the RUN_FILE_UPLOAD flag is set to true
                if ($RUN_FILE_UPLOAD === true) {
                    if ( uploadToS3($project->key, $ticket->key, $attachment->filename) ) {
                        // verify that the file exists in S3, and the reported size matches.
                        if ( !verifyRemoteFile() ) {
                            // file was not successfully uploaded.
                            msg("       - ERROR with file $attachment->filename in folder $project->key/$ticket->key");
                            msg("         the file in S3 does not match in size to the local copy.");
                        }
                        // if the remote copy is okay, and the DELETE flag is enabled, then 
                        // delete the downloaded file from Jira
                        else if ($RUN_JIRA_FILE_DELETE === true) {
                            deleteFileInJira($attachment->id);
                            msg("      - $attachment->filename (id: $attachmentId) deleted from Jira issue $ticket->key");
                        }
                    }
                    else {
                        // file was not successfully uploaded.
                        msg("       - ERROR: $project->key | $ticket->key | $attachment->filename was not uploaded to S3.");
                    }
                }
            }
        }
    }
    
    // some visual spacing between projects
    echo "\n\n";
}

msg("Done downloading files from JIRA");


?>