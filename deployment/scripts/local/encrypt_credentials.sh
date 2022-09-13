#!/bin/bash
#
#MUST be lauched from project root.
set -e

cd deployment/kubernetes

if [ ! -f  credentials_iv.txt ]; then
    openssl rand -hex 16 > credentials_iv.txt
fi

if [ ! -f  credentials_key.txt ]; then
    openssl rand -hex 32 > credentials_key.txt
fi

rm credentials.zip || true
zip -r credentials.zip credentials

export credentials_key=`cat credentials_key.txt`
export credentials_iv=`cat credentials_iv.txt`

openssl aes-256-cbc -K $credentials_key  -iv $credentials_iv -in credentials.zip  -out credentials.zip.enc -e



cd ../../
