#!/bin/bash
#

#start shell

set -e

${0%/*}/set-context.sh

kubectl exec -i -t `kubectl get pods --selector="app==base-app-portal-cron-laravel" -o=jsonpath="{.items[*]..metadata.name}"` \
	-c cron -- bash --rcfile /var/www/.bashrc

