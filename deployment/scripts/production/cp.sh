#!/bin/bash
#

#start shell

set -e

if [[ $# -ne 2 ]]; then
    echo "file names are required"
    echo "run as ${0} remote-file-path local-file-path"
    exit 1
fi
REMOTE_FILE_PATH=$1
LOCAL_FILE_PATH=$2

SCRIPT_PATH=${0%/*}

${SCRIPT_PATH}/kubectl.sh \
	cp \
	`${SCRIPT_PATH}/kubectl.sh get pods --selector="app==fdp-web" -o=jsonpath="{.items[0]..metadata.name}"`:${REMOTE_FILE_PATH} \
	${LOCAL_FILE_PATH}



