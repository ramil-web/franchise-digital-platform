#!/bin/bash

# Import env vars
if [[ "$CIRCLE_BRANCH" == "develop" ]]; then
  APP_ENV=staging
elif [[ "$CIRCLE_BRANCH" == "master" ]]; then
  APP_ENV=production
else
  APP_ENV=issue
  echo "skipping push for issue branch"
  exit 0
fi

# Import env vars
set -o allexport
source deployment/kubernetes/credentials/.env.${APP_ENV}
set +o allexport
#

# Add gcloud to $PATH
source /home/circleci/google-cloud-sdk/path.bash.inc

# Add tag
docker tag ${CONTAINER_IMAGE_NAME}:${APP_ENV} gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}:${APP_ENV}
echo "Tag set"

gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}

# Push
DIGEST=$(docker push gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}:${APP_ENV} | tail -n 1 | sed -e 's/.*\(sha256.*\) size:.*/\1/')
echo "Push finished"
echo ${DIGEST}
echo "" >> deployment/kubernetes/credentials/.env.${APP_ENV}
echo "DIGEST=${DIGEST}" >> deployment/kubernetes/credentials/.env.${APP_ENV}
