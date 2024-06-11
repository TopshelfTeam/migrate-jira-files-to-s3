#!/bin/bash

# name of the image, along with a version.
# we tag the image as "local", simply to denote it as something, since we don't have true versions here.
IMG_NAME="s3-migration"
IMG_VERSION="local"

# identify the platform we are on
# if it's an ARM based on (M1/M2/etc macs) then we run
# the docker build for a specific target
if [[ $(arch) == "arm64" ]]; then
	docker buildx build --platform linux/amd64 \
	  -t $IMG_NAME:$IMG_VERSION \
	  . 
# otherwise we run it normal
else
	docker build \
	  -t $IMG_NAME:$IMG_VERSION \
	  . 
fi
