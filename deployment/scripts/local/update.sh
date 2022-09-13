#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#refreshes dynamic resources - npm/composer etc

set -e

${0%/*}/set-context.sh

deployment/scripts/local/deploy.sh

#5. Get the public IP
theIP=$(minikube ip)
bold=$(tput bold)
normal=$(tput sgr0)

echo Local environment created successfully. You should be able to access the web pod at ${bold}"http://$theIP"${normal}. Pods may take some time to finish creating
