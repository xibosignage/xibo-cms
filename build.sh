#!/usr/bin/env bash
VERSION="latest"
while getopts v: option
do
 case "${option}"
 in
 v) VERSION=${OPTARG};;
 esac
done

echo "Building an archive for $VERSION"


# Helper script to extract a release archive from a docker container (after building or pulling it).
docker pull xibosignage/xibo-cms:"$VERSION"

echo "Pulled container"

CONTAINER=$(docker create xibosignage/xibo-cms:"$VERSION")

echo "Created container $CONTAINER"

docker cp "$CONTAINER":/var/www/cms/ "$VERSION"

echo "Copied out CMS /var/www/cms"

tar -czf "$VERSION".tar.gz "$VERSION"

echo "Tarred"

zip -rq "$VERSION".zip "$VERSION"

echo "Zipped"

docker rm "$CONTAINER"

echo "Container Removed"
echo "Please remove $VERSION folder"