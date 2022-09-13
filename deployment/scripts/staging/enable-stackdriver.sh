#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed
#based on https://github.com/kubernetes/ingress-nginx/blob/master/docs/deploy/index.md

#start shell

set -e

gcloud auth login

source deployment/kubernetes/credentials/.env.staging

gcloud container clusters update ${CLUSTER_NAME} --logging-service logging.googleapis.com --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}
