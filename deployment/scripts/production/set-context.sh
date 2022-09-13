#!/bin/bash
#
# MUST be launched from project root. Minikube must be installed

# switches minikube context

#set -e

export CLOUDSDK_CONTAINER_USE_APPLICATION_DEFAULT_CREDENTIALS=false

# use account/email for accessing cluster
USE_KEY_FILE=0

if [ $USE_KEY_FILE -ne 0 ]; then

    source deployment/kubernetes/credentials/.env.production

    gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY} > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        # repeat to show error
        echo "gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}"
        gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
        exit 1
    fi

    gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID} > /dev/null 2>&1

    if [ $? -ne 0 ]; then
        # repeat to show error
        echo "gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}"
        gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}
        exit 1
    fi
else
    # init config files
    deployment/scripts/local/configure-gcloud-access.sh

    # import env vars
    set -o allexport
    source deployment/scripts/local/.env
    if [[ -f ${HOME}/.inventcorp.env ]]; then
        # use global vars
        source ${HOME}/.inventcorp.env
    fi
    set +o allexport

    if [[ "$GCLOUD_ACCESS_EMAIL" == "not_set"
        || "$PRODUCTION_PROJECT_ID" == "not_set"
        || "$PRODUCTION_CLUSTER_NAME" == "not_set"
        || "$PRODUCTION_COMPUTE_ZONE" == "not_set"
    ]]; then
        echo "gcloud access variables not set in deployment/scripts/local/.env and/or ~/.inventcorp.env"
        exit 1
    fi

    if [[ $(gcloud auth list --format="value(account)" --filter-account=${GCLOUD_ACCESS_EMAIL}) == "${GCLOUD_ACCESS_EMAIL}" ]]; then
        gcloud config set account ${GCLOUD_ACCESS_EMAIL} > /dev/null 2>&1
    else
        gcloud auth login --brief ${GCLOUD_ACCESS_EMAIL}
    fi

    if [ $? -ne 0 ]; then
        exit 1
    fi

    gcloud container clusters get-credentials ${PRODUCTION_CLUSTER_NAME} --zone ${PRODUCTION_COMPUTE_ZONE} --project ${PRODUCTION_PROJECT_ID} > /dev/null 2>&1

    if [ $? -ne 0 ]; then
        # repeat to show error
        echo "gcloud container clusters get-credentials ${PRODUCTION_CLUSTER_NAME} --zone ${PRODUCTION_COMPUTE_ZONE} --project ${PRODUCTION_PROJECT_ID}"
        gcloud container clusters get-credentials ${PRODUCTION_CLUSTER_NAME} --zone ${PRODUCTION_COMPUTE_ZONE} --project ${PRODUCTION_PROJECT_ID}
        exit 1
    fi
fi

