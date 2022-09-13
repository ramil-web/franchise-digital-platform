#!/bin/bash
#
#MUST be lauched from project root. Minikube must be installed

#removes evicted pods that blocks other command (like bash.sh when there too many pods available)

#set -e

set -e

SCRIPT_PATH=${0%/*}

${SCRIPT_PATH}/kubectl.sh get pods -a | grep Evicted | awk '{print $1}' | xargs ${SCRIPT_PATH}/kubectl.sh delete pod
#${SCRIPT_PATH}/kubectl.sh get pods -a -n kube-system | grep Evicted | awk '{print $1}' | xargs ${SCRIPT_PATH}/kubectl.sh delete pod -n kube-system


