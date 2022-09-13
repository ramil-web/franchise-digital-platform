#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#start shell

set -e

${0%/*}/set-context.sh

kubectl exec -i -t `kubectl get pods --selector="app==fdp-web" -o=jsonpath="{.items[*]..metadata.name}"` \
	-c web -- bash --rcfile /var/www/.bashrc

