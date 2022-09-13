#!/bin/bash
exit
# rebuild base image
# run as repo/deployment/scripts/circle-ci/inventcorp-base-image-build-deploy.sh

set -e

cd repo

# Import env vars
set -o allexport
source deployment/kubernetes/credentials/.env.staging
set +o allexport

PATH=$PATH:/home/circleci/google-cloud-sdk/bin

gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
gcloud auth configure-docker --quiet

# import base image vars
set -o allexport
source deployment/kubernetes/.env.base-image
set +o allexport

echo "Start building image '${BASE_IMAGE_GCLOUD_HOST}/${BASE_IMAGE_GCLOUD_PROJECT_ID}/${BASE_IMAGE_NAME}:${BASE_IMAGE_VERSION}'"
docker build --pull -t ${BASE_IMAGE_NAME}:${BASE_IMAGE_VERSION} -f deployment/images/inventcorp-base.Dockerfile --build-arg BASE_IMAGE=${TOPMOST_BASE_IMAGE} deployment/images/

echo "Start tag image"
docker tag ${BASE_IMAGE_NAME}:${BASE_IMAGE_VERSION} ${BASE_IMAGE_GCLOUD_HOST}/${BASE_IMAGE_GCLOUD_PROJECT_ID}/${BASE_IMAGE_NAME}:${BASE_IMAGE_VERSION}

echo "Start push image"
docker push ${BASE_IMAGE_GCLOUD_HOST}/${BASE_IMAGE_GCLOUD_PROJECT_ID}/${BASE_IMAGE_NAME}:${BASE_IMAGE_VERSION}

cd ..
