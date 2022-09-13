#!/bin/bash
#
#MUST be lauched from project root.
set -e

cd deployment/kubernetes

FILE_TO_CHECK=credentials_key.txt
if [ ! -f "$FILE_TO_CHECK" ]; then
    echo "$FILE_TO_CHECK doesn't exists"
    cd ../../
    exit 1
else
    export K=`cat $FILE_TO_CHECK`
fi

FILE_TO_CHECK=credentials_iv.txt
if [ ! -f "$FILE_TO_CHECK" ]; then
    echo "$FILE_TO_CHECK doesn't exists"
    cd ../../
    exit 1
else
    export iv=`cat $FILE_TO_CHECK`
fi

#openssl aes-256-cbc -K $credentials_key -iv $credentials_iv -in credentials.zip.enc -out credentials.zip -d

openssl aes-256-cbc -K $K -iv $iv -in credentials.zip.enc -out credentials.zip -d

unzip -o credentials.zip -d .

cd ../../
