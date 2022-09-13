#!/bin/bash
#
# MUST be launched from project root.
# initializes default gcloud config files

set -e

# init local config
if [[ ! -f ${0%/*}/.env ]]; then
    cp ${0%/*}/.env.virtualbox ${0%/*}/.env
fi

if ! grep -lq '^STAGING_PROJECT_ID=' ${0%/*}/.env ; then
    # default values
    GCLOUD_ACCESS_EMAIL="not_set"

    STAGING_PROJECT_ID="not_set"
    STAGING_CLUSTER_NAME="not_set"
    STAGING_COMPUTE_ZONE="not_set"

    if [[ -f  deployment/kubernetes/credentials/.env.staging ]]; then
        # try to import values from decrypted credentials
        set -o allexport
        source deployment/kubernetes/credentials/.env.staging
        set +o allexport

        STAGING_PROJECT_ID="${GCLOUD_PROJECT_ID}"
        STAGING_CLUSTER_NAME="${CLUSTER_NAME}"
        STAGING_COMPUTE_ZONE="${CLOUDSDK_COMPUTE_ZONE}"
    fi

    PRODUCTION_PROJECT_ID="not_set"
    PRODUCTION_CLUSTER_NAME="not_set"
    PRODUCTION_COMPUTE_ZONE="not_set"

    if [[ -f  deployment/kubernetes/credentials/.env.production ]]; then
        # try to import values from decrypted credentials
        set -o allexport
        source deployment/kubernetes/credentials/.env.production
        set +o allexport

        PRODUCTION_PROJECT_ID="${GCLOUD_PROJECT_ID}"
        PRODUCTION_CLUSTER_NAME="${CLUSTER_NAME}"
        PRODUCTION_COMPUTE_ZONE="${CLOUDSDK_COMPUTE_ZONE}"
    fi

    # add gcloud access template to .env
    cat <<- DATA >> ${0%/*}/.env

# gcloud user email
GCLOUD_ACCESS_EMAIL="${GCLOUD_ACCESS_EMAIL}"

# staging config
STAGING_PROJECT_ID="${STAGING_PROJECT_ID}"
STAGING_CLUSTER_NAME="${STAGING_CLUSTER_NAME}"
STAGING_COMPUTE_ZONE="${STAGING_COMPUTE_ZONE}"

# production config
PRODUCTION_PROJECT_ID="${PRODUCTION_PROJECT_ID}"
PRODUCTION_CLUSTER_NAME="${PRODUCTION_CLUSTER_NAME}"
PRODUCTION_COMPUTE_ZONE="${PRODUCTION_COMPUTE_ZONE}"
DATA
fi

