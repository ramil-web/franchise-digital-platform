#!/bin/bash

# JS and Style lint errors don't have to drop the build at this moment. Enable if you want to enforce linting
#set -e

#extract list of changed files, from circle commits lists
#CIRCLE_COMPARE_URL=https://github.com/inventcorp/q360/compare/89f7ec74e455...c30588bb9051

COMMITS=0
if [[ "${CIRCLE_COMPARE_URL}" =~ /([^/]*)$ ]]; then
    echo "CIRCLE_COMPARE_URL : ${CIRCLE_COMPARE_URL}"
	COMMITS="${BASH_REMATCH[1]}"
    if [[ ! "${COMMITS}" =~ \.\. ]]; then
        COMMITS="${COMMITS}^.."
    fi
else
  echo "Can't find commits, please check if variable CIRCLE_COMPARE_URL is set. ${CIRCLE_COMPARE_URL}"
  exit 0
fi

CHANGED_FILES_PHP=(`git log "${COMMITS}" --diff-filter=d --name-only --pretty=format: | grep -E '\.php$' | sort | uniq`)

CHANGED_FILES_JS=(`git log "${COMMITS}" --diff-filter=d --name-only --pretty=format: | grep -E '\.js$|\.vue$' | sort | uniq`)

CHANGED_FILES_CSS=(`git log "${COMMITS}" --diff-filter=d --name-only --pretty=format: | grep -E '\.css$|\.scss$' | sort | uniq`)

if [ ${#CHANGED_FILES_JS[@]} -eq 0 ]; then
    echo "No changed JS files found."
else
    echo "Validating changed JS files:"
    printf "%s\n" "${CHANGED_FILES_JS[@]}"
    printf "%s\0" "${CHANGED_FILES_JS[@]}" | xargs -0 -n1 -P4 -I {} sh -c 'if [ -f "{}" ]; then ./node_modules/.bin/eslint "{}" ; fi'
fi

if [ ${#CHANGED_FILES_CSS[@]} -eq 0 ]; then
    echo "No changed Style files found."
else
    echo "Validating changed Style files:"
    printf "%s\n" "${CHANGED_FILES_CSS[@]}"
    printf "%s\0" "${CHANGED_FILES_CSS[@]}" | xargs -0 -n1 -P4 -I {} sh -c 'if [ -f "{}" ]; then ./node_modules/.bin/stylelint "{}" ; fi'
fi


if [ ${#CHANGED_FILES_PHP[@]} -eq 0 ]; then
   echo "No changed PHP files found."
else
    echo "Validating changed PHP files:"
    printf "%s\n" "${CHANGED_FILES_PHP[@]}"
    echo "phpcs"
    printf "%s\n" "${CHANGED_FILES_PHP[@]}" | egrep -v "^(./config/|./bootstrap/|./vendor/|./storage/)" | xargs -n1 -P4 -I {} sh -c 'if [ -f "{}" ]; then ./vendor/bin/phpcs -q -s "{}" ; fi'
    echo "phpmd"
    printf "%s\n" "${CHANGED_FILES_PHP[@]}" | egrep -v "^(./config/|./bootstrap/|./vendor/|./storage/)" | xargs -n1 -P4 -I {} sh -c 'if [ -f "{}" ]; then ./vendor/bin/phpmd --ignore-violations-on-exit "{}" text ruleset ; fi'
    set -e
    printf "%s\0" "${CHANGED_FILES_PHP[@]}" | xargs -0 -n1 -P4 -I {} sh -c 'if [ -f "{}" ]; then php -l -n "{}" ; fi' | (! grep -v "No syntax errors detected" )
fi
