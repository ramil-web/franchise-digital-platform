#!/bin/bash
exit
# rebuild circleci image
# run as repo/deployment/scripts/circle-ci/circleci-image-build-deploy.sh

set -e

cd repo

# Import env vars
set -o allexport
source deployment/kubernetes/credentials/.env.staging
source deployment/kubernetes/.env.circleci-image
set +o allexport

PATH=$PATH:/home/circleci/google-cloud-sdk/bin

gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
gcloud auth configure-docker --quiet

echo "Start build cirlcecli image '${GCLOUD_HOST}/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION}'"
docker build --pull -t ${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION} -f deployment/images/circleci.Dockerfile --build-arg BASE_IMAGE=${BASE_IMAGE} deployment/images/

echo "Start tag cirlcecli image"
docker tag ${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION} ${GCLOUD_HOST}/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION}

echo "Start push cirlcecli image"
docker push ${GCLOUD_HOST}/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION}

cd ..
