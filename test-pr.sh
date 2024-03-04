#!/usr/bin/env bash
#
# Copyright (C) 2024 Xibo Signage Ltd
#
# Xibo - Digital Signage - https://xibosignage.com
#
# This file is part of Xibo.
#
# Xibo is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# any later version.
#
# Xibo is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
#
while getopts p:d: option
do
 case "${option}"
 in
 p) PR_NUMBER=${OPTARG};;
 d) DELETE_MODE=${OPTARG};;
 esac
done

if [ "$DELETE_MODE" == "true" ]
then
  echo "Deleting containers"
  docker stop test-pr-web && docker rm test-pr-web
  docker stop test-pr-xmr && docker rm test-pr-xmr
  docker stop test-pr-db && docker rm test-pr-db
  exit
fi

docker pull mysql:8
docker pull ghcr.io/xibosignage/xibo-xmr:latest
docker pull ghcr.io/xibosignage/xibo-cms:test-"$PR_NUMBER"

docker run --name test-pr-db \
  -e MYSQL_RANDOM_ROOT_PASSWORD=yes \
  -e MYSQL_DATABASE=cms \
  -e MYSQL_USER=cms \
  -e MYSQL_PASSWORD=jenkins \
  -d \
  mysql:8

docker run --name test-pr-xmr -d ghcr.io/xibosignage/xibo-xmr:latest

# Run the CMS container, adjusting env for CI and copying back in PHP Unit
docker run --name test-pr-web \
  -e MYSQL_HOST=test-pr-db \
  -e MYSQL_USER=cms \
  -e MYSQL_PASSWORD=jenkins \
  -e CMS_DEV_MODE=true \
  -e XMR_HOST=test-pr-xmr \
  -e CMS_USAGE_REPORT=false \
  -e INSTALL_TYPE=ci \
  -e MYSQL_BACKUP_ENABLED=false \
  --link test-pr-db \
  --link test-pr-xmr \
  -d \
  ghcr.io/xibosignage/xibo-cms:test-"$PR_NUMBER"

echo "Containers starting, waiting for ready event"

docker exec -t test-pr-web /bin/bash -c "/usr/local/bin/wait-for-command.sh -q -t 300 -c \"nc -z localhost 80\""
docker exec -t test-pr-web /bin/bash -c "chown -R www-data.www-data /var/www/cms"
docker exec --user www-data -t test-pr-web /bin/bash -c "cd /var/www/cms; /usr/bin/php bin/run.php 1"

sleep 5

echo "CMS running"

