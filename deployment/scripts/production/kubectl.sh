#!/bin/bash
#
#start shell

set -e

SCRIPT_PATH=${0%/*}

${0%/*}/set-context.sh 

kubectl $@


