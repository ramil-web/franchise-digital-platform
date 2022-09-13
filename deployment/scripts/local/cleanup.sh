#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

set -e

${0%/*}/set-context.sh

read -p "All data in minikube will be deleted. Continue (y/n)?" choice
case "$choice" in 
  y|Y ) ;;
  n|N ) exit;;
  * ) echo "invalid";exit;;
esac

echo "deleting data from minikube cluster"

kubectl delete service --all
kubectl delete deployment --all
kubectl delete cronjob --all
kubectl delete job --all
kubectl delete ingress --all
kubectl delete statefulset --all
kubectl delete pods --all
kubectl delete persistentvolumeclaim --all
kubectl delete persistentvolume --all
kubectl delete secret rabbitmq-config
