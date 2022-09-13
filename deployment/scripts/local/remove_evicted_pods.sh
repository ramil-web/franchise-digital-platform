#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#removes evicted pods that blocks other command (like bash.sh when there too many pods available)

#set -e

${0%/*}/set-context.sh

kubectl get pods -a | grep Evicted | awk '{print $1}' | xargs kubectl delete pod
kubectl get pods -a -n kube-system | grep Evicted | awk '{print $1}' | xargs kubectl delete pod -n kube-system
