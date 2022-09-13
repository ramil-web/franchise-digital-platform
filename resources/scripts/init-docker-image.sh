#!/bin/bash
#initializes Laravel

set -e

cp .env.${APP_ENVIRONMENT} .env

php artisan config:cache
php artisan route:cache

