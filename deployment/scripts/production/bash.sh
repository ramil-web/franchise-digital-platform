#!/bin/bash
#
#MUST be launched from project root. Minikube must be installed

#start shell

set -e

${0%/*}/set-context.sh

kubectl exec -i -t `kubectl get pods --selector="app==fdp-web" -o=jsonpath="{.items[0]..metadata.name}"` \
	-c web -- bash --rcfile /var/www/.bashrc
