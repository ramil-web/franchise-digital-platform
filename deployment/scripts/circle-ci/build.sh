#!/bin/bash

set -e

# Import env vars
if [[ "$CIRCLE_BRANCH" == "develop" ]]; then
  APP_ENV=staging
  BASE_IMAGE_TAG=${APP_ENV}
elif [[ "$CIRCLE_BRANCH" == "master" ]]; then
  APP_ENV=production
  BASE_IMAGE_TAG=${APP_ENV}
else
  APP_ENV=issue
  BASE_IMAGE_TAG=staging
fi

set -o allexport
source deployment/kubernetes/credentials/.env.${APP_ENV}
set +o allexport

# Add gcloud to $PATH
source /home/circleci/google-cloud-sdk/path.bash.inc
gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
gcloud auth configure-docker --quiet

echo "Authenticated"

# optimization
cp .dockerignore.circleci .dockerignore

# init newrelic
envsubst < deployment/configs/newrelic.template > deployment/configs/newrelic.ini
echo -e "extension = \"newrelic.so\"\n$(cat deployment/configs/newrelic.ini)" > deployment/configs/newrelic.ini

# Build flow
BASE_IMAGE="gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}-${BASE_IMAGE_TAG}-base:${IMAGE_VERSION}"
docker build -t ${CONTAINER_IMAGE_NAME}:${APP_ENV} -f deployment/images/cluster.Dockerfile --build-arg WEB_CONF=${WEB_CONF} --build-arg BASE_IMAGE=${BASE_IMAGE} .


echo "Build finished"
