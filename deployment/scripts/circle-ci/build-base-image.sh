#!/bin/bash

# MUST be lauched from project root.
# run as repo/deployment/scripts/circle-ci/build-base-image.sh
# rebuild project specific base images for staging and production

set -e

cd repo

PATH=$PATH:/home/circleci/google-cloud-sdk/bin

# import base image vars
set -o allexport
source deployment/kubernetes/.env.base-image
set +o allexport

# build project specific base image
echo ${inventcorp_base_images_key_json} | docker login -u _json_key --password-stdin https://${BASE_IMAGE_GCLOUD_HOST}

echo "Start build project specific base image"
# build intermediate image, with project specific changes
BASE_IMAGE_PATH=${BASE_IMAGE_GCLOUD_HOST}/${BASE_IMAGE_GCLOUD_PROJECT_ID}/${BASE_IMAGE_NAME}:${BASE_IMAGE_VERSION}
docker image build -t inventcorp-project-image -f deployment/images/project.Dockerfile --build-arg BASE_IMAGE=${BASE_IMAGE_PATH} deployment/images/

docker logout https://${BASE_IMAGE_GCLOUD_HOST}

## Import env vars
set -o allexport
source deployment/kubernetes/credentials/.env.staging
set +o allexport

gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
gcloud auth configure-docker --quiet

echo "Start build base image"
docker build -t ${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION} -f deployment/images/cluster-base.Dockerfile --build-arg BASE_IMAGE=inventcorp-project-image deployment/images/

echo "Start tag base image"
docker tag ${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION} gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}-staging-base:${IMAGE_VERSION}

echo "Start push base image"
docker push gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}-staging-base:${IMAGE_VERSION}

#prod image

# Import env vars
set -o allexport
source deployment/kubernetes/credentials/.env.production
set +o allexport

gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
gcloud auth configure-docker --quiet

echo "Start build base image"
docker build -t ${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION} -f deployment/images/cluster-base.Dockerfile --build-arg BASE_IMAGE=inventcorp-project-image deployment/images/

echo "Start tag base image"
docker tag ${CONTAINER_IMAGE_NAME}:${IMAGE_VERSION} gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}-production-base:${IMAGE_VERSION}

echo "Start push base image"
docker push gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}-production-base:${IMAGE_VERSION}

cd ..
