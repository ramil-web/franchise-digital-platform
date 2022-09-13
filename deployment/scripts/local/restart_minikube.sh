#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed
set -e

if [ ! -f ${0%/*}/.env ]; then
    cp ${0%/*}/.env.virtualbox ${0%/*}/.env
fi

# Import env vars
set -o allexport
source ${0%/*}/.env
set +o allexport

${0%/*}/stop.sh

if [[ "$LOCAL_MINIKUBE_DRIVER" == "docker" ]]; then
    #restart docker as well
    sleep 10
    sudo systemctl stop docker
    sudo systemctl start docker
    sleep 10
fi

${0%/*}/minikube.sh
