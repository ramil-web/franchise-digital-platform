#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#start shell

set -e

gcloud compute --project "pp-sxope-downloader-staging" ssh --zone "us-east4-b" "gke-staging-laravel--staging-laravel--651ad8bb-nx94"
