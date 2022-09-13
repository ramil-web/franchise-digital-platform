#!/bin/bash
#
#MUST be launched from project root. Minikube must be installed
#based on https://github.com/kubernetes/ingress-nginx/blob/master/docs/deploy/index.md

#start shell

set -e

gcloud auth login

source deployment/kubernetes/credentials/.env.production

gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}

kubectl create clusterrolebinding cluster-admin-binding --clusterrole cluster-admin --user $(gcloud config get-value account) || true

kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/nginx-0.30.0/deploy/static/mandatory.yaml
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/nginx-0.30.0/deploy/static/provider/cloud-generic.yaml
kubectl get pods --all-namespaces -l app.kubernetes.io/name=ingress-nginx


name=nginx-ingress-controller

containers=$(kubectl get pods   --namespace=ingress-nginx -o=jsonpath='{.items[?(@.spec..containers[:1].name=="'$name'")].metadata.name}')

for contName in $containers; do

    echo "checking $contName in $name"

    status=$(kubectl get pods   --namespace=ingress-nginx $contName -o jsonpath="{.status.phase}" )

    while [[ $status != "Running" && $status != "Terminating" ]]
        do
            echo waiting for $contName to finish creating, status : $status
            echo "$(kubectl get pods   --namespace=ingress-nginx)"
            sleep 10
            status=$(kubectl get pods   --namespace=ingress-nginx $contName -o jsonpath="{.status.phase}" )
        done

    echo $contName created successfully

done

echo nginx ingress is ready

kubectl get pods --all-namespaces -l app.kubernetes.io/name=ingress-nginx
