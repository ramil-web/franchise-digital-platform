#!/bin/bash
#
#MUST be launched from project root. Minikube must be installed

#start shell

set -e

gcloud compute --project "pp-sxope-downloader-staging" ssh --zone "us-east4-b" "gke-production-larav-staging-laravel--31b1d6d3-46hw"
