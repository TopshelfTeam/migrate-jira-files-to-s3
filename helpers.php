<?php

// require 'aws/aws-autoloader.php';
require '/aws-sdk-php/vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;



// --- --- --- --- --- --- --- --- --- --- --- --- 
// HELPER FUNCTIONS
// --- --- --- --- --- --- --- --- --- --- --- --- 

// wrapper function, to retrieve all projects from Jira
// note: follows pagination and returns all results
function getProjects($projectKeys) {
    global $REST_URL;
        
    $pagesize = 50;
    $start = 0;
    
    $projects = [];
    $total = 0;
    
    // repeatedly call the project search api, until we've retrieved all projects
    do {
        // set the url params with pagination data
        $params = "startAt=$start&maxResults=$pagesize";
        
        // if we have a specific list of projects, add it to the url params
        if (!empty($projectKeys)) {
            $params .= "&keys=" . implode('&keys=' , $projectKeys);
        }
    
        // construct the full url
        $url = "$REST_URL/project/search?$params";
                
        // grab projects from jira
        $temp = json_decode(callJIRA($url));
        // var_dump($temp);
        
        // update the total projects
        $total = $temp->total;
        
        // merge the result we just grabbed into the full set of projects
        $projects = array_merge($temp->values, $projects);
        
        // bump the start, so the next iteration picks up the next set of tickets
        $start += $pagesize;
    }
    // run this until we have enough tickets to match the total available
    while ($total > count($projects));
    
    return $projects;
}

// wrapper function to retrieve all tickets which match our JQL query
// by default, this means all tickets for a given Jira project
// which have attachments
function getTickets($projectKey) {
    global $REST_URL, $PROJECT_ATTACHMENT_JQL;
    
    // construct the base JQL query (without any pagination data)
    $jql = urlencode(
        sprintf($PROJECT_ATTACHMENT_JQL, $projectKey)
    );
    
    $pagesize = 50;
    $start = 0;
    
    $tickets = [];
    $total = 0;
    
    // repeatedly call the search api, until we've retrieved all tickets
    do {
        // build the url
        $url = "$REST_URL/search/?jql=$jql&fields=id,key,attachment&startAt=$start&maxResults=$pagesize";
        
        // grab tickets from jira
        $temp = json_decode(callJIRA($url));
        
        // var_dump($temp);
        
        // update the total tickets
        $total = $temp->total;
        
        // merge the result we just grabbed into the full set of tickets
        $tickets = array_merge($temp->issues, $tickets);
        
        // bump the start, so the next iteration picks up the next set of tickets
        $start += $pagesize;
    }
    // run this until we have enough tickets to match the total available
    while ($total > count($tickets));
    
    return $tickets;
}


// download a specific file from a specific ticket
// note: we create the local folder for the project and/or ticket if it doesn't exist yet.
function downloadFile($projectKey, $ticketKey, $fileName, $attachmentUrl) {
    global $ATLASSIAN_AUTH_TOKEN;
    global $LOCAL_DOWNLOAD_PATH;
    
    $path = "$LOCAL_DOWNLOAD_PATH/$projectKey/$ticketKey";
    
    // create a folder for the ticket, if we don't already have one
    if ( !file_exists("$path") ) {
        mkdir("$path", 0777, true);
    }
    
    set_time_limit(0);
    
    //This is the file where we save the    information
    $fp = fopen ($path."/".$fileName , 'w+');
    
    // create curl resource 
    $ch = curl_init(); 
    
    // set url 
    curl_setopt($ch, CURLOPT_URL, $attachmentUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // set JSON headers for the REST api
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Basic $ATLASSIAN_AUTH_TOKEN"
    ));
    
    // curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    
    // write curl response to file
    curl_setopt($ch, CURLOPT_FILE, $fp); 
    
    // get curl response
    curl_exec($ch); 
    
    // close curl
    curl_close($ch);
    
    // close the file
    fclose($fp);
} 

// verify that a given file we stored locally matches the size of the file
// we see in Jira Cloud
function verifyLocalFile($projectKey, $ticketKey, $fileName, $size) {
    global $LOCAL_DOWNLOAD_PATH;
    
    $path = "$LOCAL_DOWNLOAD_PATH/$projectKey/$ticketKey/$fileName";
    
    $actualSize = filesize($path);
    
    if ( $actualSize == $size ) {
        // echo "Filesize matches";
        return true;
    }
    else {
        echo "Filesize does not match. Expected $size but got $actualSize";
        return false;
    }
}

function verifyRemoteFile() {
    // return false;
    return true;
    // ($projectKey, $ticketKey, $fileName, $attachmentUrl)
}

// upload a locally stored file from a ticket to S3
// the file is uploaded into a folder based on project key + issue key.
// Thus a file on ticket ABC-1 would be uploaded to ABC/ABC-1/some_file_here.png
function uploadToS3($projectKey, $ticketKey, $filename) {
    global $LOCAL_DOWNLOAD_PATH;
    global $S3_PATH_PREFIX;
    global $S3_CLIENT;
    global $S3_BUCKET_NAME;
    
    // construct the path to the local copy of the file we need to upload
    $local_path = "$LOCAL_DOWNLOAD_PATH/$projectKey/$ticketKey/$filename";
    
    // construct the remote path, where we will store the file in S3
    // NOTE: this must be a relative path, otherwise S3 hates us
    if (empty($S3_PATH_PREFIX)) {
        $remote_path = "$projectKey/$ticketKey/$filename";
    }
    else {
        $remote_path = "$S3_PATH_PREFIX/$projectKey/$ticketKey/$filename";
    }
    
    // echo "LOCAL:  $local_path \n";
    // echo "REMOTE: $remote_path \n";
    
    // Using stream instead of file path
    $source = fopen($local_path, 'rb');

    $uploader = new ObjectUploader( $S3_CLIENT, $S3_BUCKET_NAME, $remote_path, $source );

    do {
        try {
            $result = $uploader->upload();
            if ($result["@metadata"]["statusCode"] == '200') {
                msg("       '$filename' successfully uploaded to " . $result["ObjectURL"]);
            }
            // DEBUG:
            // print($result);
            
            return true;
        } 
        catch (MultipartUploadException $e) {
            rewind($source);
            $uploader = new MultipartUploader($s3Client, $source, [
                'state' => $e->getState(),
            ]);
            
            return false;
        }
    } while (!isset($result));
}



function deleteFileInJira($attachmentId) {
    global $REST_URL;
    
    $url = "$REST_URL/attachment/$attachmentId";
    
    $result = callJIRA($url, "DELETE");
    
    // msg("      - '$attachmentId' deleted from Jira");
}



// call the Jira API for a given url
// we automatically inject the atlassian auth token into the request
function callJIRA($url, $type = "GET") {
    global $ATLASSIAN_AUTH_TOKEN;
        
    // create curl resource 
    $ch = curl_init(); 

    // set url 
    curl_setopt($ch, CURLOPT_URL, $url);

    //return the transfer as a string 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

    // set JSON headers for the REST api
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Basic $ATLASSIAN_AUTH_TOKEN",
        'Accept: application/json',
        'Content-Type: application/json'
    ));
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);

    // curl_setopt($ch, CURLOPT_VERBOSE, true);

    // $output contains the output string 
    $output = curl_exec($ch); 

    return $output;

    // close curl resource to free up system resources 
    curl_close($ch); 
}




function msg($text, $skipNewLine = false) {
    global $WRITE_MESSAGES_TO_LOG_FILE, $LOG_FILE;
    
    $currentDate = date("Y-m-d H:i:s");
    $message = "$currentDate - $text" . ($skipNewLine ? "" : "\n");
    echo $message;
    
    if ($WRITE_MESSAGES_TO_LOG_FILE === true) {
        file_put_contents($LOG_FILE, $message, FILE_APPEND);
    }
}

function inlinemsg($text, $addNewLine = false) {
    echo "$text" . ($addNewLine ? "\n" : "") ;
}


?>