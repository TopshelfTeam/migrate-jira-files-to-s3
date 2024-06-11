#!/bin/bash

# the command to execute when the container starts
# if blank, then we execute a default command, which is the php script. Otherwise we use the command supplied to us.
# NOTE: this is mainly for debugging, so we can easily bash into the container.
CMD="${1:-php migrate_files_to_s3.php}"

# settings for which docker image / version to use. You need to execute build.sh first.
IMG_NAME="s3-migration"
IMG_VERSION="local"

# the name of the container when it's running.
CONTAINER_NAME="s3migration-test"

# run the container, and attach to it. When stopped, we tear down the container and remove it.
# Note that we bind-mount the parent folder where the scripts are into /scripts folder in the container.
# The script will download files into that folder. if you change the download location in config.php, 
# make sure the container has access to it.
docker run \
  --name $CONTAINER_NAME \
  -it \
  --rm \
  -v "${PWD}/../":/script \
  $IMG_NAME:$IMG_VERSION $CMD
