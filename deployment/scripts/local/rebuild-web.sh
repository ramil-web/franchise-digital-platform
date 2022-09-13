#!/bin/bash
#
#MUST be launched from project root. Minikube must be installed

#rebuilds image and recreates the web pod and the load balancer.
# Useful when changing web-related configs and settings

set -e

eval $(minikube docker-env --shell bash)

${0%/*}/set-context.sh

cp .dockerignore.local .dockerignore
docker image build -t fa:1.0 -f deployment/images/local.Dockerfile .

kubectl patch deployment fdp-app-web -p \
  "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"date\":\"`date +'%s'`\"}}}}}"

kubectl patch deployment fdp-app-cron-laravel -p \
  "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"date\":\"`date +'%s'`\"}}}}}"

kubectl patch deployment fdp-app-queue-general -p \
  "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"date\":\"`date +'%s'`\"}}}}}"

kubectl patch deployment fdp-app-queue-notifications -p \
  "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"date\":\"`date +'%s'`\"}}}}}"

kubectl delete -f deployment/kubernetes/items/local/web.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/web.yaml

kubectl delete -f deployment/kubernetes/items/local/load_balancer.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/load_balancer.yaml

deployment/scripts/local/info.sh
