#!/usr/bin/env bash
#
# Copyright (C) 2025 Xibo Signage Ltd
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

# Default values
SERVER_PORT=80

while getopts p:d:s: option; do
  case "${option}" in
    p) PR_NUMBER=${OPTARG};;
    d) DELETE_PORT=${OPTARG};;
    s) SERVER_PORT=${OPTARG};;
  esac
done

# Create a network if it doesn't exist
NETWORK_NAME="test-pr-network"
docker network inspect "$NETWORK_NAME" >/dev/null 2>&1 || docker network create "$NETWORK_NAME"

if [ "$DELETE_PORT" == "all" ]; then
  echo "Deleting all test containers..."

  # Stop and remove all test-pr-* containers
  docker ps -a --format '{{.Names}}' | grep "^test-pr-" | while read -r container_name; do
    docker stop "$container_name" && docker rm "$container_name"
  done

  # Remove network if no containers are using it
  docker network rm $NETWORK_NAME

  exit
elif [ -n "$DELETE_PORT" ]; then
  echo "Deleting containers for port $DELETE_PORT..."

  # Stop and remove containers associated with the specific SERVER_PORT
  docker ps -a --format '{{.Names}}' | grep "test-pr-.*-$DELETE_PORT" | while read -r container_name; do
    docker stop "$container_name" && docker rm "$container_name"
  done

  # Remove network if no containers are using it
  remaining_containers=$(docker ps -a --format '{{.Names}}' | grep "^test-pr-" | wc -l)
  if [ "$remaining_containers" -eq 0 ]; then
    docker network rm $NETWORK_NAME
  fi

  exit
fi


# Pull necessary Docker images
echo "Pulling Docker images..."
docker pull mysql:8
docker pull ghcr.io/xibosignage/xibo-xmr:latest
docker pull ghcr.io/xibosignage/xibo-cms:test-"$PR_NUMBER"
docker pull mongo:4.2

# Run the MySQL container
docker run --name test-pr-db-"$SERVER_PORT" \
  --network "$NETWORK_NAME" \
  -e MYSQL_RANDOM_ROOT_PASSWORD=yes \
  -e MYSQL_DATABASE=cms \
  -e MYSQL_USER=cms \
  -e MYSQL_PASSWORD=jenkins \
  -d \
  mysql:8

# Check if MongoDB container exists before creating
if ! docker ps -a --format '{{.Names}}' | grep -q "test-pr-mongo"; then
  echo "Starting new MongoDB container..."
  docker run --name test-pr-mongo \
    --network "$NETWORK_NAME" \
    -e MONGO_INITDB_ROOT_USERNAME=root \
    -e MONGO_INITDB_ROOT_PASSWORD=example \
    -d \
    -p 27071:27071 \
    mongo:4.2
else
  echo "MongoDB container already exists, skipping creation."
fi

docker run --name test-pr-xmr-"$SERVER_PORT" -d ghcr.io/xibosignage/xibo-xmr:latest

# Run the CMS container
docker run --name test-pr-web-"$SERVER_PORT" \
  --network "$NETWORK_NAME" \
  -e MYSQL_HOST=test-pr-db-"$SERVER_PORT" \
  -e MYSQL_USER=cms \
  -e MYSQL_PASSWORD=jenkins \
  -e CMS_DEV_MODE=true \
  -e XMR_HOST=test-pr-xmr-"$SERVER_PORT" \
  -e CMS_USAGE_REPORT=false \
  -e INSTALL_TYPE=ci \
  -e MYSQL_BACKUP_ENABLED=false \
  --link test-pr-db-"$SERVER_PORT" \
  --link test-pr-xmr-"$SERVER_PORT" \
  --link test-pr-mongo \
  -p "$SERVER_PORT":80 \
  -d \
  ghcr.io/xibosignage/xibo-cms:test-"$PR_NUMBER"

echo "Containers starting, waiting for ready event"

docker exec -t test-pr-web-"$SERVER_PORT" /bin/bash -c "/usr/local/bin/wait-for-command.sh -q -t 300 -c \"nc -z localhost 80\""
docker exec -t test-pr-web-"$SERVER_PORT" /bin/bash -c "chown -R www-data.www-data /var/www/cms"
docker exec --user www-data -t test-pr-web-"$SERVER_PORT" /bin/bash -c "cd /var/www/cms; /usr/bin/php bin/run.php 1"

#
## Enable Currency Module
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE module SET enabled = 1, previewEnabled = 1  WHERE moduleId = 'core-currencies'"
#
## Update CMS Secret Key
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE \`setting\` SET \`value\`='2eqb4sp1' WHERE \`setting\`='SERVER_KEY' LIMIT 1"
#
## Enable Stocks Module
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE module SET enabled = 1, previewEnabled = 1  WHERE moduleId = 'core-stocks'"
#
## Stocks and Currency APIKEY with Alpha Vantage
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE connectors SET isEnabled = 1, settings = '{\"apiKey\":\"N6CWL6CXSLUDXAWK\",\"isPaidPlan\":0,\"cachePeriod\":3600}' WHERE connectorId =6"

## pixabay
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE connectors SET isEnabled = 1, settings = '{\"apiKey\":\"22666304-606f0cfa93854d20faf7edee\"}' WHERE connectorId = 1"
#
## Enable Weather Module
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE module SET enabled = 1, previewEnabled = 1  WHERE moduleId = 'core-forecastio'"
#
## Setup APIKEY with Open Weather Map
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "UPDATE connectors SET isEnabled = 1, settings = '{\"owmApiKey\":\"0cddfb55e2f3ee6c107149bea476bc5a\",\"owmIsPaidPlan\":1,\"cachePeriod\":3600}' WHERE connectorId = 7"
#
## Run SEED DB Task to populate data
docker exec test-pr-db-"$SERVER_PORT" mysql -ucms -pjenkins cms -e "INSERT INTO task (name, class, status, isActive, configFile, options, schedule) VALUES ('Seed Database', '\\\\Xibo\\\\XTR\\\\SeedDatabaseTask', 2, 1, '/tasks/seed-database.task', '{}', '* * * * * *')"
docker exec --user www-data -t test-pr-web-"$SERVER_PORT" /bin/bash -c "cd /var/www/cms; /usr/bin/php bin/run.php \"Seed Database\""
# Run SEED Database Task
docker exec --user www-data -t test-pr-web-"$SERVER_PORT" /bin/bash -c "cd /var/www/cms; /usr/bin/php bin/run.php 19"
#docker exec --user www-data -t test-pr-web-"$SERVER_PORT" /bin/bash -c "cd /var/www/cms; vendor/bin/phinx migrate"

#
## Run Widget Sync Task To load data in Dataset schedules on the player
#docker exec --user www-data -t test-pr-web-"$SERVER_PORT" /bin/bash -c "cd /var/www/cms; /usr/bin/php bin/run.php 9"
## Run Report Schedule Task To generate saved report for proff of play report
#docker exec --user www-data -t test-pr-web-"$SERVER_PORT" /bin/bash -c "cd /var/www/cms; /usr/bin/php bin/run.php 10"


# Enable Xibo Exchange in Application
#Go to Application and enable it manually

sleep 5

echo "CMS running on port $SERVER_PORT"
