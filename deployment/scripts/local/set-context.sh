#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#switches kubectl context

set -e

kubectl config use-context minikube > /dev/null
