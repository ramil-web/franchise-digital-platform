#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#shortcut for artisan shell command

set -e

${0%/*}/set-context.sh

COMMAND="cd /var/www/ && \
        php artisan $@"

#echo $COMMAND

kubectl exec -i -t `kubectl get pods --selector="app==fdp-web" -o=jsonpath="{.items[*]..metadata.name}"` \
	-c web -- bash -c "$COMMAND"

