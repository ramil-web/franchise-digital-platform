#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

# provides access to cluster mysql at port 33063

set -e

${0%/*}/set-context.sh

while true
do
    kubectl port-forward  `kubectl get pods -l role=cloud-sql-proxy -o=jsonpath='{.items[0].metadata.name}'` 33063:3306
done


