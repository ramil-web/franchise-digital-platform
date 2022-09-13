#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed
#based on https://github.com/kubernetes/ingress-nginx/blob/master/docs/deploy/index.md

#start shell

set -e

gcloud auth login

source deployment/kubernetes/credentials/.env.staging

gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}

kubectl create -f deployment/kubernetes/items/staging/falco-account.yaml
kubectl create -f deployment/kubernetes/items/staging/falco-service.yaml
kubectl create configmap falco-config --from-file=deployment/kubernetes/items/staging/falco-config
kubectl create -f deployment/kubernetes/items/staging/falco-daemonset-configmap.yaml
