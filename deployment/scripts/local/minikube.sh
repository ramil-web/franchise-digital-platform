#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed
set -e

echo "Starting minikube ..."

if [ ! -f ${0%/*}/.env ]; then
    cp ${0%/*}/.env.virtualbox ${0%/*}/.env
fi

# Import env vars
set -o allexport
source ${0%/*}/.env
set +o allexport

if [[ "$LOCAL_MINIKUBE_DRIVER" == "virtualbox" ]]; then

    minikube start --vm-driver=virtualbox --memory $LOCAL_MINIKUBE_VIRTUALBOX_MEMORY --kubernetes-version $LOCAL_MINIKUBE_KUBERNETES_VERSION

elif [[ "$LOCAL_MINIKUBE_DRIVER" == "docker" ]]; then

    export MINIKUBE_WANTUPDATENOTIFICATION=false
    export MINIKUBE_WANTREPORTERRORPROMPT=false
    export MINIKUBE_HOME=$HOME
    export CHANGE_MINIKUBE_NONE_USER=true
    export KUBECONFIG=$HOME/.kube/config
    sudo minikube start --vm-driver=none --kubernetes-version $LOCAL_MINIKUBE_KUBERNETES_VERSION \
        $LOCAL_MINIKUBE_EXTRA_ARGS \
        --apiserver-ips 127.0.0.1 --apiserver-name localhost

    # this for loop waits until kubectl can access the api server that Minikube has created
    for i in {1..150}; do # timeout for 5 minutes
       kubectl get po &> /dev/null
       if [ $? -ne 1 ]; then
          break
      fi
      sleep 2
    done

    #bug in 28.2, manually untaint https://github.com/kubernetes/minikube/issues/3028
    kubectl taint node minikube node-role.kubernetes.io/master:NoSchedule- || true

    #enable ingress, avoid bug with  minikube addons enable ingress 
    if [ ! -f /etc/kubernetes/addons/ingress-configmap.yaml ]; then
        CURRENT_DIR="$PWD"
        sudo wget https://github.com/kubernetes/minikube/raw/master/deploy/addons/ingress/ingress-dp.yaml
        sudo wget https://github.com/kubernetes/minikube/raw/master/deploy/addons/ingress/ingress-svc.yaml
        sudo wget https://github.com/kubernetes/minikube/raw/master/deploy/addons/ingress/ingress-configmap.yaml
        cd $CURRENT_DIR
    fi

else
    echo "Unknown driver $LOCAL_MINIKUBE_DRIVER"
    exit 1
fi
