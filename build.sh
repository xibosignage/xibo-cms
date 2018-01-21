#!/usr/bin/env bash

while getopts r:t: option
do
 case "${option}"
 in
 v) VERSION=${OPTARG};;
 esac
done

# Helper script to extract a release archive from a docker container (after building or pulling it).
docker build . -t cms-build-test

CONTAINER=$(docker create cms-build-test)

docker cp "$CONTAINER":/var/www/cms "$VERSION"

tar -czvf ./"$VERSION" "$VERSION".tar.gz
zip -r "$VERSION".zip ./"$VERSION"
rm -R ./"$VERSION"

docker rm "$CONTAINER"

