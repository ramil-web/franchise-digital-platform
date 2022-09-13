#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

set -e

${0%/*}/set-context.sh

#re-install pods
kubectl apply -f deployment/kubernetes/items/local/load_balancer.yaml
kubectl apply -f deployment/kubernetes/items/local/rabbitmq-deployment.yaml
kubectl apply -f deployment/kubernetes/items/local/rabbitmq-external-service.yaml

#there is old redis without persistent data
kubectl delete replicationcontroller redis > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/redis.yaml

kubectl apply -f deployment/kubernetes/items/local/web.yaml
#force reload
kubectl patch deployment fdp-app-web -p \
  "{\"spec\":{\"template\":{\"metadata\":{\"annotations\":{\"date\":\"`date +'%s'`\"}}}}}"

kubectl apply -f deployment/kubernetes/items/local/web-ingress.yaml

kubectl delete deployment fdp-app-cron-laravel > /dev/null 2>&1 || true
kubectl delete -f deployment/kubernetes/items/local/cron-laravel.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/cron-laravel.yaml

kubectl delete -f deployment/kubernetes/items/local/queue-general.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/queue-general.yaml

kubectl delete -f deployment/kubernetes/items/local/queue-notifications.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/local/queue-notifications.yaml

deployment/scripts/local/info.sh
