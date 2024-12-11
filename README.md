[![Xibo - Digital Signage](web/theme/default/img/xibologo.png)](https://xibosignage.com)

[![Github All Releases](https://img.shields.io/github/downloads/xibosignage/xibo-cms/total.svg)]()


## About Xibo
Xibo is a powerful Open Source Digital Signage platform with a web content management system and Windows display player
software. We have commercial player options for Android, LG webOS and Samsung Tizen, as well as CMS hosting and support.

See [https://xibosignage.com](https://xibosignage.com) for more information.

Our first open source release 1.0.0-rc1 landed in 2009, and we're committed to keeping everything you need to run a
digital signage network, or single screen, open source and free to use.

## Licence

[![Licence](https://img.shields.io/github/license/xibosignage/xibo-cms)]()

Copyright (C) 2006-2024 Xibo Signage Ltd and Contributors.

Xibo is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public
License as published by the Free Software Foundation, either version 3 of the License, or any later version.

Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with Xibo. 
If not, see <http://www.gnu.org/licenses/>.


# Installation

We recommend installing an official release via Docker. Instructions for doing so can be found in our 
[documentation](https://xibosignage.com/docs/setup/cms-installation-guides).


# Developing

**Please only install a Development environment if you intend make code changes to Xibo. Installing from the 
repository is not suitable for a production installation.**

Xibo uses Docker to ensure all contributors have a repeatable development environment which is easy to get up and
running. The very same Docker containers are used in our recommended end user installation to promote consistency 
from development to deployment.

To these ends this repository includes a `docker-compose.yml` file to spin up a model development environment.


## Prerequisites

The development Docker containers do not automatically build vendor files for PHP or JS, this is left as a developer 
responsibility. Therefore you will need the following tools:

 - Git
 - [Composer](http://getcomposer.org)
 - NodeJS version 12
 - npm
 - Docker


## Clone the repository

Create a folder in your development workspace and clone the repository. If you intend to make changes and submit
pull requests please Fork us first and create a new branch.

```shell
git clone git@github.com:<your_id>/xibo-cms.git xibo-cms
```

### Branches

We maintain the following branches. To contribute to Xibo please use the `develop` branch as your base.

- kopff: Work in progress toward 4.2.x
- develop: Bug fixes for 4.1.x
- master: Currently 4.0
- release40: Bug fixes for 4.0
- release33: Bug fixes for 3.3
- release23: Bug fixes for 2.3
- release18: Archive of 1.8
- release17: Archive of 1.7
- release1.6.4: Archive of 1.6

## Install dependencies

Change into your new folder

```sh
cd xibo-cms
```

We recommend installing the dependencies via Docker, so that you are guarenteed consistent dependencies across 
different development machines.

### PHP dependencies

```shell
docker run --interactive --tty --volume $PWD:/app --volume ~/.composer:/tmp composer install
```

This command also mounts the Composer `/tmp` folder into your home directory so that you can take advantage of
Composer caching.

### Website dependencies (webpack)

If you have installed node locally:

```shell
npm install webpack -g
npm install
npm run build
```

Alternatively you can use a Docker container:

```shell
docker run -it --volume $PWD:/app --volume ~/.npm:/root/.npm -w /app node:22 sh -c "npm install webpack -g; npm install; npm run build;"
```

### Mapped Volumes

The development version of Xibo expects the code base to be mapped into the container such that changes on the host
are reflected in the container.

However, the container itself creates some files, such as the twig cache and library uploads. These locations will need
to be created and the container given access to them.

The easiest way to do this is to make the `cache` and `library` folders and `chmod 777` them. Obviously this is not
suitable for production, but you shouldn't be using these files for production (we have containers for that).

### API Keys
The API requires a pub/private RSA keypair and an encryption key to be provided. The docker entrypoint will create 
these in `/library/certs`.

You can override the generated keys paths and encryption key by providing an alternative in `settings-custom.php`.
For example: 

```php
$apiKeyPaths = [
    'publicKeyPath' => '/var/www/cms/custom/public.key',
    'privateKeyPath' => '/var/www/cms/custom/private.key',
    'encryptionKey' => ''
];
```

### OpenOOH specification
Xibo can present the OpenOOH venue classifications in the display edit form. For this functionality to work in 
development, it is necessary 
to [download the latest file](https://raw.githubusercontent.com/openooh/venue-taxonomy/main/specification.json) and 
place it in here: `openooh/specification.json`

The production/CI containers add this file during the build process so that it is already available in the Docker
image.


## Bring up the Containers

Use Docker Compose to bring up the containers.

```sh
docker-compose up --build -d
```

## Login
After the containers have come up you should be able to login with the details:

U: `xibo_admin`
P: `password`


## Translations
To parse the translations:

```shell
docker-compose exec web sh -c "cd /var/www/cms; rm -R ./cache"
docker-compose exec web sh -c "cd /var/www/cms; php bin/locale.php"
```

```shell
find ./locale ./cache ./lib ./web  -iname "*.php" -print0 | xargs -0 xgettext --from-code=UTF-8 -k_e -k_x -k__ -o locale/default.pot
```

To import translations:

```shell
bzr pull lp:~dangarner/xibo/holmes-translations
```

Convert to `mo` format:

```shell
for i in *.po; do msgfmt "$i" -o $(echo $i | sed s/po/mo/); done
```

Move the resulting `mo` files into your `locale` folder.

## Swagger API Docs
To generate a `swagger.json` file, with the dev containers running:

```shell
docker-compose exec web sh -c "cd /var/www/cms; vendor/bin/swagger lib -o web/swagger.json"
```

## Application Structure

To find out more about the application code and how everything fits together, please refer to 
the [developer docs](https://xibosignage.com/docs/developer/extend).


# Contributing
We would be delighted to accept contributions to the project - please refer to
[CONTRIBUTING.md](https://github.com/xibosignage/xibo/blob/master/CONTRIBUTING.md) for further information.

# Sponsorship
We've built commercial products and services on top of our open source project. If you want to support our work the
best way is to [become a customer](https://xibosignage.com/pricing). We're committed to keeping our project open
source either way!

# Reporting Problems
Support requests can be reported on the [Xibo Community Forum](https://community.xibo.org.uk/c/dev). Verified, 
re-producable bugs with this repository can be reported in 
the [Xibo parent repository](https://github.com/xibosignage/xibo/issues).
