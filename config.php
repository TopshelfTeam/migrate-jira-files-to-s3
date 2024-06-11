<?php

    // --- --- --- --- --- --- --- --- --- --- --- --- 
    // ATLASSIAN SETTINGS
    // --- --- --- --- --- --- --- --- --- --- --- --- 

    // This is the url to your JIRA instance
    // Should be HTTPS, otherwise we have to deal with redirects
    $JIRA_BASE_URL = "https://topshelf1.atlassian.net";

    // username and API KEY for your JIRA instance.
    // This should probably be an administrator that can see all projects
    // NOTE: username should be an email for Jira cloud
    $ATLASSIAN_USERNAME = "<jira cloud username/email here>";
    $ATLASSIAN_API_KEY = "<api token here>";
        
    
    // --- --- --- --- --- --- --- --- --- --- --- --- 
    // JIRA SETTINGS
    // --- --- --- --- --- --- --- --- --- --- --- --- 

    // which projects we should run the export for.
    // by default, we include all projects (an empty array here means we are not filtering).
    // if you only want to export files for specific project(s), then fill in the array, like this:
    // $projectKeys = ["ABC", "TLH"];
    $projectKeysFilter = [];
    
    // the JQL query we use to find issues with attachments in a given project.
    // NOTE: the is a sprintf expression, and the project key is automatically filled in.
    //       if you want to limit this further, make sure it is a valid JQL query  
    //       by testing it in jira first.
    $PROJECT_ATTACHMENT_JQL = "PROJECT = '%s' AND attachments is not EMPTY";


    // --- --- --- --- --- --- --- --- --- --- --- --- 
    // S3 SETTINGS
    // --- --- --- --- --- --- --- --- --- --- --- --- 

    // the S3 bucket to upload files to, and the region where the bucket is located.
    // NOTE: this is just the bucket name, without the s3:// prefix. 
    //       example: tss-cloudfiles-virginia
    $S3_BUCKET_NAME = '<bucket name goes here>';
    $S3_BUCKET_REGION = "us-west-1";

    // aws keys for accessing the bucket
    // note: these keys need write access to the bucket to be able to upload files
    $AWS_ACCESS_KEY = "<AWS Access key here>";
    $AWS_SECRET_KEY = "<AWS secret key here>";

    // where in the S3 bucket to upload the files to.
    // Note: this is empty by default, since Simple Cloud Files doesn't set a prefix by default,
    //       which means folders for each project are created in the root of the bucket.
    //       See the README file for a sample folder structure.
    //
    //       If you set a value, it must be a relative path. Thus, not starting with a slash.
    //       Example:  "sample_folder". Not "/sample_folder" and not "./sample_folder"
    $S3_PATH_PREFIX = "";


    // --- --- --- --- --- --- --- --- --- --- --- --- 
    // LOCAL FILE STORAGE
    // --- --- --- --- --- --- --- --- --- --- --- --- 

    // This the path where downloaded files should be saved to
    // NOTE: this can be an absolute path, or a relative path to the php script
    // NOTE 2: THIS PATH MUST EXIST ALREADY!
    $LOCAL_DOWNLOAD_PATH = "./jira_files";

    // where to log progress messages, etc
    $LOG_FILE = "./output.txt";

    // --- --- --- --- --- --- --- --- --- --- --- --- 
    // FEATURE FLAGS
    // --- --- --- --- --- --- --- --- --- --- --- --- 
    $WRITE_MESSAGES_TO_LOG_FILE = true;
    $RUN_FILE_UPLOAD = true;
    $RUN_JIRA_FILE_DELETE = false;


?>