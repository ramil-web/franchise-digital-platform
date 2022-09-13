#!/bin/bash

set -e

# Import env vars
if [[ "$CIRCLE_BRANCH" == "develop" ]]; then
  export APP_ENV=staging
elif [[ "$CIRCLE_BRANCH" == "master" ]]; then
  export APP_ENV=production
else
  export APP_ENV=issue
  echo "skipping deploy for issue branch"
  exit 0
fi

# Import env vars
set -o allexport
source deployment/kubernetes/credentials/.env.${APP_ENV}
set +o allexport
#

if [[ -z "$DIGEST" ]]; then
    echo "Digest is empty. Possible push failure."
    exit 100;
fi

# Add gcloud to $PATH
source /home/circleci/google-cloud-sdk/path.bash.inc

# Get kubectl access
gcloud container clusters get-credentials ${CLUSTER_NAME} --zone ${CLOUDSDK_COMPUTE_ZONE} --project ${GCLOUD_PROJECT_ID}

echo "$(kubectl get pods)"

# Update third services
kubectl apply -f deployment/kubernetes/items/${APP_ENV}/redis.yaml
kubectl apply -f deployment/kubernetes/items/${APP_ENV}/rabbitmq-deployment.yaml

# Update secrets with credentials
kubectl create secret generic cloudsql-credentials --from-file=credentials.json=${GCLOUD_CLOUD_SQL_KEY} --dry-run -o yaml | kubectl apply -f -

kubectl create secret generic application-credentials --from-file=credentials.json=${GCLOUD_APPLICATION_KEY} --dry-run -o yaml | kubectl apply -f -

# apply parameters to templated yamls
echo "Process templates in yaml"
export POD_IMAGE="gcr.io/${GCLOUD_PROJECT_ID}/${CONTAINER_IMAGE_NAME}:${APP_ENV}@${DIGEST}"
echo "New image: ${POD_IMAGE}"
export DO_NOT_APPLY_IT_DIRECTLY=''

export GCLOUD_APPLICATION_KEY="/secrets/application/credentials.json"

SUBST_FORMAT='
    ${GCLOUD_PROJECT_ID}
    ${GCLOUD_APPLICATION_KEY}
    ${POD_IMAGE}
    ${APP_ENV}
    ${DO_NOT_APPLY_IT_DIRECTLY}
    ${SUPERVISOR_SERVER_USER}
    ${SUPERVISOR_SERVER_PASSWORD}
    ${PUSHER_APP_ID}
    ${PUSHER_APP_KEY}
    ${PUSHER_APP_SECRET}
    ${PUSHER_APP_CLUSTER}
    ${DB_USERNAME}
    ${DB_PASSWORD}
    ${DB_DATABASE}
    ${DB_HOST}
    ${GCLOUD_CLOUD_SQL_INSTANCE}
    ${NEW_RELIC_APP_NAME}
    ${NEW_RELIC_LICENSE}
    ${NEW_RELIC_API_KEY}
    ${JWT_TTL}
    ${JWT_REFRESH_TTL}
    ${JWT_ALGO}
    ${JWT_SECRET}
    ${SENDGRID_API_KEY}
    ${GOOGLE_MAPS_API_KEY}
    ${GOOGLE_AUTH_CLIENT_ID}
    ${GOOGLE_AUTH_CLIENT_SECRET}
    ${LINKEDIN_AUTH_CLIENT_ID}
    ${LINKEDIN_AUTH_CLIENT_SECRET}
    ${MAIL_USERNAME}
    ${MAIL_PASSWORD}
    ${INTERNAL_SHARED_GOOGLE_CLOUD_STORAGE_BUCKET}
    ${HUBSPOT_API_KEY}
    ${HUBSPOT_PORTAL_ID}
    ${STRIPE_API_KEY}
    ${ADMIN_HTTPS}
    ${BIGPICTURE_API_KEY}
    ${BRANDFETCH_API_KEY}
    '
YAML_PATH="deployment/kubernetes/items/${APP_ENV}"
TEMPLATES=(
    cloud-proxy
    migrations
    web
    cron-laravel
    queue-general
    queue-notifications
)

for TEMPLATE in "${TEMPLATES[@]}"
do
    envsubst "${SUBST_FORMAT}" \
        < ${YAML_PATH}/${TEMPLATE}.yaml \
        > ${YAML_PATH}/${TEMPLATE}_updated.yaml
    cp ${YAML_PATH}/${TEMPLATE}_updated.yaml ${YAML_PATH}/${TEMPLATE}.yaml
done

echo "Rollout cloud sql proxy"

kubectl apply -f ./deployment/kubernetes/items/${APP_ENV}/cloud-proxy.yaml

# Force rollout on each deploy to refresh pod (clear logs etc)
#kubectl patch deployment cloud-sql-proxy -p \
#  "{\"spec\":{\"template\":{\"metadata\":{\"labels\":{\"date\":\"`date +'%s'`\"}}}}}"

#Keep running until cloud sql proxy are up
#kubectl rollout status deployments cloud-sql-proxy

echo "Start migrations"

kubectl delete job fdp-app-laravel-migrations > /dev/null 2>&1 || true
kubectl create -f deployment/kubernetes/items/${APP_ENV}/migrations.yaml
deployment/scripts/circle-ci/wait-job-completion.sh fdp-app-laravel-migrations
echo "Migrations applied"

#laravel cron - no need for rolling updates, just drop and start
kubectl delete -f deployment/kubernetes/items/${APP_ENV}/cron-laravel.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/${APP_ENV}/cron-laravel.yaml

kubectl apply -f ./deployment/kubernetes/items/${APP_ENV}/web.yaml
kubectl rollout status deployments fdp-web

#queues - no need for rolling updates, just drop and start
kubectl delete -f deployment/kubernetes/items/${APP_ENV}/queue-general.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/${APP_ENV}/queue-general.yaml

kubectl delete -f deployment/kubernetes/items/${APP_ENV}/queue-notifications.yaml > /dev/null 2>&1 || true
kubectl apply -f deployment/kubernetes/items/${APP_ENV}/queue-notifications.yaml

echo "Deployments updated"

if [[ "$APP_ENV" == "staging" ]]; then
    # ingress added only one time, when doesn't exist
    INGRESSES=($(kubectl get ingress -o jsonpath="{.items[*].metadata.name}"))
    if [[ ! " ${INGRESSES[@]} " =~ " tls-web-ingress " ]]; then
        # deploy load balancer only once
        echo "Create ingress"
        kubectl apply -f deployment/kubernetes/items/${APP_ENV}/load_balancer.yaml
    fi
fi

# Force cron-job restart
kubectl delete job --all
kubectl delete cronjobs --all

echo "Waiting till all pods are running"
checkTries=1
pendingPods=$(kubectl get pods | tail -n +2 | grep -v Running || true)
while [[ -n "${pendingPods}"  ]]
	do
        checkTries=$(( $checkTries + 1 ))
        if [[ $checkTries -gt 120 ]]; then
            echo "Waited when all pods in running status for 20 minutes without success, exist"
            exit 100;
        fi
        echo `date` waiting for pods to start running
        echo "${pendingPods}"
        sleep 10
        pendingPods=$(kubectl get pods | tail -n +2 | grep -v Running || true)
	done

echo "All pods are running"

echo "$(kubectl get pods || true)"
echo "Waiting till all pods are ready"
podNames=$(kubectl get pods -o=jsonpath="{.items[*]..metadata.name}" || true)
for podName in $podNames; do
    status=$(kubectl get pods $podName -o jsonpath="{.status.containerStatuses[:1].ready}" || true )
    checkTries=1
    while [[ $status != "true" ]]
        do
            checkTries=$(( $checkTries + 1 ))
            if [[ $checkTries -gt 120 ]]; then
                echo "Waited when all pods in ready status for 20 minutes without success, exist"
                exit 100;
            fi
            echo waiting for $podName to finish creating, status : $status
            echo "$(kubectl get pods $podName || true)"
            sleep 10
            status=$(kubectl get pods $podName -o jsonpath="{.status.containerStatuses[:1].ready}" || true )
        done
        echo $podName is ready
done
echo "All pods are ready"

echo "$(kubectl get pods)"
echo "$(kubectl get pv)"
echo "$(kubectl get pvc)"

# Set deployment label in newrelic dashboard
curl -H "x-api-key:${NEW_RELIC_API_KEY}" \
    -d "deployment[application_id]=${NEW_RELIC_APP_NAME}" \
    -d "deployment[description]=FDP Deployment" \
    -d "deployment[revision]=${CIRCLE_SHA1}" \
    https://api.newrelic.com/deployments.xml

#echo optimize opcache
#
#podNames=$(kubectl get pods --selector="app==fdp-web" -o=jsonpath="{.items[*]..metadata.name}")
#
#for podName in $podNames; do
#
#    status=$(kubectl get pods $podName -o jsonpath="{.status.containerStatuses[:1].ready}" || true )
#    phase=$(kubectl get pods $contName -o jsonpath="{.status.phase}"  || true )
#
#    if [[ "$status" == "true" && $phase != "Terminating" ]]; then
#        echo "Running opcache optimization for ${podName}"
#        kubectl exec $podName -c web -- php artisan opcache:compile --force || true
#    fi
#done
