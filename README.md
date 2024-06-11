# migrate-jira-files-to-s3

## Description:

This is a helper script that connects to a Jira Cloud instance, goes through all projects (or just the desired projects) and finds any issues that have attachments. 

It then downloads the attachments into a local folder structure that Simple Cloudfiles understands, and then uploads the files to a desired S3 bucket. Optionally, it then deletes the file from Jira Cloud.


## Notes:

- The local file structure will be:

        ./jira_files
          --/ABC                   <-- Project Key
          ----/ABC-1               <--- Issue Key
          ------/some_file.png     <-- Downloaded File, by filename


- If you do not wish for this script to upload files directly to S3, change the `$RUN_FILE_UPLOAD` flag in `config.php`.  
    In this case, the script will simply download files locally in `$LOCAL_DOWNLOAD_PATH`, and you can then upload them to S3 yourself, 
    using any tool of your choice. 
    - Note that the script performs validation before it runs and expects S3 related settings (bucket name, credentials, etc) to be filled in. If you go this path, just use dummy values, so the script can proceed with the download.

- By default, deleting of files from Jira Cloud is disabled, as we do not want this script to be destructive.  
    If you want to delete the files from Jira Cloud after they have been uploaded to S3, change the `$RUN_JIRA_FILE_DELETE` flag in `config.php`.  
    This will then delete the file from Jira after it has been uploaded to S3.

  
- You need to fill in the required values in the `config.php` file.
    - details about your Jira Cloud instance + Atlassian credentials. This means, the email you use to login, as well as an API key.  
      You can obtain an API key by visiting Manage Account -> Security -> Create and Manage API Tokens

    - details about your S3 bucket + AWS credentials (key + secret key)

    - which projects you want to export attachments for (default is all projects. But you can limit it to a specific set of project keys)
      note: the settings contain a JQL query used to find issues with attachments. If you want to further limit issues (beyond just project key), you can change the JQL query to add more conditions.

## Prerequisites

- you can run this script in a docker container by using the provided `build.sh` file (to build the image) and `run.sh` (to run the container)

- if you want to run it locally, without Docker, then you will need PHP CLI, as well as the AWS PHP SDK, which can be found here:
    https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html.  
    Follow the steps for "Installing by Using the ZIP file" (download and unzip into the same diretory as this script). 

    -  IMPORTANT NOTE:  if you go this path, update the `require '/aws-sdk-php/vendor/autoload.php';` line in the `migrate_files_to_s3.php` file and the the helpers.php file to point to the autoload in your local downloaded version.


## Building the docker image

You can build the container using the `build.sh` file provided, or using this command from the `docker` folder:

	docker build -t s3-migration:local .

The docker image is standalone, and basically takes the PHP CLI, and installs composer and the latest AWS PHP SDK.  
The docker image **does not** include any of the scripts. It only provides a convenient place for us to run the script from.

## Running the script

You can run the script manually via `php migrate_files_to_s3.php`, or by using the docker image.  
You can run the container using the `run.sh` file in the `docker` folder. This simply bind-mounts the main folder into the docker image, and then executes  `php migrate_files_to_s3.php` inside the container.  

Note that downloaded files will be written to the local folder, outside of the container.

You can also use this docker command:

    docker run \
      --name "s3migration-test" \
      -it \
      --rm \
      -v "${PWD}/../":/script \
      s3-migration:local php migrate_files_to_s3.php


## IMPORTANT REMINDER:

If you migrate files from Jira Cloud to S3, and you delete the source files from Jira Cloud, then this will break any file mentions/file links/file embeds.

This means, if a user embedded an image in an issue field or comment, that image will no longer show up if the underlying file is removed.  
The same is true for any file links in issue fields or comments.

**This script does not alter any issue fields or comments to point the files to S3.**

