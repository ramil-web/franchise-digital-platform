#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#rebuilds image

set -e

if [ ! -f ${0%/*}/.env ]; then
    cp ${0%/*}/.env.virtualbox ${0%/*}/.env
fi

# Import env vars
set -o allexport
source ${0%/*}/.env
set +o allexport
if [[ "$LOCAL_MINIKUBE_DRIVER" == "virtualbox" ]]; then
    eval $(minikube docker-env --shell bash)
fi

${0%/*}/set-context.sh

cp .dockerignore.local .dockerignore
docker image build -t fa:1.0 -f deployment/images/local.Dockerfile --build-arg DOCKER_USER_ID=${DOCKER_USER_ID} --build-arg DOCKER_GROUP_ID=${DOCKER_GROUP_ID} .

#rebuild resources
kubectl delete -f deployment/kubernetes/items/local/init-laravel.yaml  > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/init-laravel.yaml
deployment/scripts/local/wait-job-completion.sh init-laravel

kubectl apply -f deployment/kubernetes/items/local/web.yaml
kubectl patch deployment fdp-app-web -p \
  "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"date\":\"`date +'%s'`\"}}}}}"


kubectl delete -f deployment/kubernetes/items/local/cron-laravel.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/cron-laravel.yaml

kubectl delete -f deployment/kubernetes/items/local/queue-general.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/queue-general.yaml

kubectl delete -f deployment/kubernetes/items/local/queue-notifications.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/queue-notifications.yaml

deployment/scripts/local/info.sh
