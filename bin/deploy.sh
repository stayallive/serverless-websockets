#!/bin/bash

echo "?> To which stage do you want to deploy?"
read -p "Stage: " STAGE

if [ -z "$STAGE" ]
then
  echo "!> No stage input, aborting..."
  exit 0
fi

echo ""
echo "=> Optimizing composer dependencies"
echo ""

composer install --prefer-dist --optimize-autoloader --classmap-authoritative --no-dev

echo ""
echo "=> Deploying to stage: $STAGE"
echo ""

serverless deploy --stage=$STAGE

echo ""
echo "=> Reinstalling composer dev dependencies"
echo ""

composer install
