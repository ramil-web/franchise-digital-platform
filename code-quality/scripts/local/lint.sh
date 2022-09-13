#!/bin/bash

set -e

echo "Linting all PHP, JS, CSS and SCSS files, except for vendor packages..."

echo "\n JS/VUE:"

find . -type f -name "*.js" -o -name "*.vue" | egrep -v "^(./vendor/|./node_modules/|./public/vendor/|./public/js/app.js|./public/js/vendor.js)" | xargs -I {} sh -c "./node_modules/.bin/eslint {}"

echo "\n CSS/SCSS: \n"

find . -type f -name "*.css" -o -name "*.scss" | egrep -v "^(./vendor/|./node_modules/|./public/vendor/|./public/js/app.css)" | xargs -I {} sh -c "./node_modules/.bin/stylelint {}"

echo "Files checked successfully."
