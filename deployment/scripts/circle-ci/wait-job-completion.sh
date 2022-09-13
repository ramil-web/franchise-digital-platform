#!/usr/bin/env bash
#
# waits while job is completed

set -o errexit   # exit on error
set -o nounset   # fail if var undefined
set -o noclobber # don't overwrite exists files via >
set -o pipefail  # fails if pipes (|) fails
# set -o xtrace  # debug

job="$1"
echo "waiting while ${job} job is complete"
sleep 5

exists=$( kubectl get job "${job}" )
# shellcheck disable=SC2034
for i in {1..12}
do
    if [[ -n "$exists" ]]
    then
        echo "$( date ) ${job} is started"
        break
    fi
    echo "$( date ) waiting for ${job} to start"
    sleep 5
    exists=$( kubectl get job "${job}" )
done

if [[ -z "$exists" ]]
then
    echo "$( date ) ${job} not started in 60 sec, failing"
    exit 1
fi

active=$( kubectl get job "${job}" -o=jsonpath="{.status.active}" )

while [[ -n $active  ]]
    do
        echo "$( date ) waiting for ${job} to finish running"
        sleep 5
        kubectl logs -f "job/${job}" || true
        active=$( kubectl get job "${job}" -o=jsonpath="{.status.active}" )
    done

succeeded=$(kubectl get job "${job}" -o=jsonpath="{.status.succeeded}")

if [[ -n $succeeded  ]]
then
    echo "job ${job} finished successfully"
    exit 0
fi

failed=$(kubectl get job "${job}" -o=jsonpath="{.status.failed}")

if [[ -n $failed  ]]
then
    echo "job ${job} failed"

    #trying to get logs
    kubectl logs -f "job/${job}" || true
    exit 1
fi

echo "job $job not found, failing"
exit 1
