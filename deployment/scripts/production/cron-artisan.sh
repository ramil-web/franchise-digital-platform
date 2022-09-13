#!/bin/bash
#
#MUST be launched from project root. Minikube must be installed

#shortcut for artisan shell command

set -e

${0%/*}/set-context.sh

COMMAND="cd /var/www/ && \
        php artisan $@"

#echo $COMMAND

kubectl exec -i -t `kubectl get pods --selector="app==base-app-portal-cron-laravel" -o=jsonpath="{.items[*]..metadata.name}"` \
	-c web -- bash -c "$COMMAND"
