#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed
#based on https://github.com/kubernetes/ingress-nginx/blob/master/docs/deploy/index.md

#start shell

set -e

gcloud auth login

source deployment/kubernetes/credentials/.env.staging

gcloud logging sinks create siemlogsinkpubsub \
    pubsub.googleapis.com/projects/pp-secops-staging/topics/gpsiemnginxpubsubtopic \
    --log-filter "resource.type=\"container\" AND logName=(\"projects/${GCLOUD_PROJECT_ID}/logs/falco\" OR \"projects/${GCLOUD_PROJECT_ID}/logs/nginx-ingress-controller\")" \
    --project ${GCLOUD_PROJECT_ID}

