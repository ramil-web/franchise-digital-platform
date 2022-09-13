#!/bin/bash
#
#MUST be launched from project root. Minikube must be installed

#start shell

set -e

${0%/*}/set-context.sh

kubectl create secret tls tls-franchise123-com --cert=deployment/configs/self-signed-certificate/tls.crt --key=deployment/configs/self-signed-certificate/tls.key


