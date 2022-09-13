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

if [[ "$LOCAL_MINIKUBE_DRIVER" == "virtualbox" ]]; then

    minikube stop || true

elif [[ "$LOCAL_MINIKUBE_DRIVER" == "docker" ]]; then

    minikube stop
    #minikube may don't work, so do it directly

    ###ignore all errors: on
    set +e
    sudo systemctl stop kubelet
    #sudo systemctl restart docker

    docker stop $(docker ps -q --filter name=k8s)
    docker rm $(docker ps -aq --filter name=k8s)

    sudo umount $(mount | grep kubelet | grep secret | cut -d' ' -f3)

    ###ignore all errors: off
    set -e

else
    echo "Unknown driver $LOCAL_MINIKUBE_DRIVER"
    exit 1
fi

