#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#switches minikube context

#set -e

USE_KEY_FILE=1

source deployment/kubernetes/credentials/.env.staging

if [ $USE_KEY_FILE -ne 0 ]; then

    export CLOUDSDK_CONTAINER_USE_APPLICATION_DEFAULT_CREDENTIALS=false

    gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY} > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        #repeat to show error
        echo "gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}"
        gcloud auth activate-service-account --key-file ${GCLOUD_DEPLOYMENT_KEY}
        exit 1
    fi

    gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}  > /dev/null 2>&1

    if [ $? -ne 0 ]; then
        #repeat to show error
        echo "gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}"
        gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}
        exit 1
    fi
else
    kubectl config use-context gke_${GCLOUD_PROJECT_ID}_${CLOUDSDK_COMPUTE_ZONE}_${CLUSTER_NAME}  > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}  > /dev/null 2>&1

        if [ $? -ne 0 ]; then
            #repeat to show error
            echo "gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}"
            gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}
            exit 1
        fi
    fi
fi

