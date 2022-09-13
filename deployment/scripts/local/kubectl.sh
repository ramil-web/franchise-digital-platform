#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

set -e

${0%/*}/set-context.sh

kubectl $@

