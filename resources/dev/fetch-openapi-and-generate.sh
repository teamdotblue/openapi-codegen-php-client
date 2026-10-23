#!/bin/bash
set -e

OPENAPI=${1:-''}

## CHECK IF ALL VARIABLES ARE SET
if [ -z $OPENAPI ]; then
    echo "No open api spec location was found. Add the url or path as parameter."; exit 1;
fi

## FETCH OPEN API SPEC
if [ -f $OPENAPI ]; then
  if [ $OPENAPI != "resources/api/api-spec.json" ]; then
    mv $OPENAPI resources/api/api-spec.json;
  fi;
else
  wget --no-check-certificate -cq $OPENAPI -O - | jq --indent 4 '.' > resources/api/api-spec.json;
fi;

chmod -R 777 resources/api

git checkout -B feature/client-generation
vendor/bin/openapi-codegen-php-client.sh

git stash -- resources/api/api-spec.json || true
if [ -z "$(git status --porcelain)" ]; then echo "No changes found during a new generate."; exit 0; fi
git stash apply
git add .
git commit -m "Automatic update client by CI"
git remote -v
git push origin feature/client-generation:$CI_COMMIT_REF_NAME --force
exit 1;