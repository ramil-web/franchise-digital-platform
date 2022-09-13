#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#waits while deploy is completed

deploy=$1
echo waiting while $deploy deploy is running

sleep 1
active=$(kubectl get deploy $deploy -o=jsonpath="{.status.readyReplicas}")

while [[ $active -eq 0 ]]
    do
        echo `date` waiting for $deploy to activate
        sleep 5
        kubectl logs deploy/${deploy} || true
        active=$(kubectl get deploy $deploy -o=jsonpath="{.status.readyReplicas}")
    done


echo $deploy deploy is running
