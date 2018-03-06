#!/usr/bin/env bash

# A simple helper script to run composer
# useful if your dev host environment doesn't have PHP
# on windows replace $PWD with your working repo root folder
docker run --rm \
    --volume $PWD:/app \
    composer install --ignore-platform-reqs --optimize-autoloader