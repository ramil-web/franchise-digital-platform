#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#shortcut for yiic shell command

set -e

if [ ! -f ${0%/*}/.env ]; then
    cp ${0%/*}/.env.virtualbox ${0%/*}/.env
fi

# Import env vars
set -o allexport
source ${0%/*}/.env
set +o allexport

${0%/*}/set-context.sh


COMMAND="cd /var/www/ && \
        sudo -u \#${DOCKER_USER_ID} php artisan $@"

#echo $COMMAND

kubectl exec -i -t `kubectl get pods --selector="app==fdp-app-web" -o=jsonpath="{.items[*]..metadata.name}"` \
	-- bash -c "$COMMAND"

