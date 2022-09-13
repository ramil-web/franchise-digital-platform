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

#1. Create Minikube VM and Docker image
minikube config set WantReportErrorPrompt false
${0%/*}/stop.sh
sleep 10

echo "Starting minikube ..."
${0%/*}/minikube.sh
minikube addons enable ingress

if [[ "$LOCAL_MINIKUBE_DRIVER" == "virtualbox" ]]; then
    eval $(minikube docker-env --shell bash)
fi

${0%/*}/set-context.sh

cp .dockerignore.local .dockerignore
docker image build -t fa:1.0 -f deployment/images/local.Dockerfile --build-arg DOCKER_USER_ID=${DOCKER_USER_ID} --build-arg DOCKER_GROUP_ID=${DOCKER_GROUP_ID} .

myreadlink() { [ ! -h "$1" ] && echo "$1" || (local link="$(expr "$(command ls -ld -- "$1")" : '.*-> \(.*\)$')"; cd $(dirname $1); myreadlink "$link" | sed "s|^\([^/].*\)\$|$(dirname $1)/\1|"); }
sshpath=$(myreadlink $HOME/.ssh)

if [[ "$LOCAL_MINIKUBE_DRIVER" == "virtualbox" ]]; then

    ${0%/*}/stop.sh
    sleep 5

    VBoxManage sharedfolder remove minikube --name "mount-fa"  > /dev/null 2>&1 || true
    sleep 1
    VBoxManage sharedfolder add minikube --name "mount-fa" --hostpath "$PWD" --automount
    sleep 1
    VBoxManage setextradata minikube VBoxInternal2/SharedFoldersEnableSymlinksCreate/mount-fa 1

    ${0%/*}/minikube.sh

elif [[ "$LOCAL_MINIKUBE_DRIVER" == "docker" ]]; then
    # recreate links in / to current folder
    CURRENT_DIR="$PWD"
    cd /
    if [ -L mount-fa ]; then
        sudo rm mount-fa
    fi
    sudo ln -s -T $CURRENT_DIR mount-fa
    cd $CURRENT_DIR
else
    echo "Unknown driver $LOCAL_MINIKUBE_DRIVER"
    exit 1
fi

if [ ! -f .env ]; then
    cp .env.local .env
fi

if [ ! -f .env ]; then
    cp .env.local .env
fi

#3. Deploy db
sleep 10
kubectl apply -f deployment/kubernetes/items/local/mysql-deployment.yaml
kubectl apply -f deployment/kubernetes/items/local/mysql-external-service.yaml

#4. Install resources
chmod -R 0777 ./bootstrap/cache
touch ./storage/logs/laravel.log
chmod -R 0777 ./storage

sleep 10

kubectl delete -f deployment/kubernetes/items/local/init-laravel.yaml > /dev/null 2>&1  || true
kubectl apply -f deployment/kubernetes/items/local/init-laravel.yaml

sleep 10

echo "Please wait: Laravel initialization - composer/migrations (~6min-20min)"
deployment/scripts/local/wait-job-completion.sh init-laravel

#5. Install pods
deployment/scripts/local/deploy.sh

deployment/scripts/local/info.sh
