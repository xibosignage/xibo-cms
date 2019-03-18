# Introduction
Xibo - Digital Signage - https://xibo.org.uk
Copyright (C) 2006-2019 Xibo Signage Ltd and Contributors.



#### Branches

- develop: Work in progress toward 2.1
- master: Currently 2.0
- release18: Work in progress toward the next 1.8
- release17: Work in progress toward the next 1.7
- release1.6.4: Archive of 1.6



## Licence
Xibo is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or
any later version.

Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with Xibo.  If not, see <http://www.gnu.org/licenses/>.



# Installation

Installing an official release is [described in the manual](http://xibo.org.uk/manual/en/install_cms.html) and in the release notes of each release.



# Developing

**Please only install a Development environment if you intend make code changes to Xibo. Installing from the repository is not suitable for a production installation.**

Xibo uses Docker to ensure all contributers have a repeatable development environment which is easy to get up and running.

The very same Docker containers are used in our recommended end user installation to promote consistency from development to deployment.

To these ends this repository includes a `docker-compose.yml` file to spin up a model development environment.



## Prerequisites

The development Docker containers do not automatically build vendor files for PHP or JS, this is left as a developer responsibility. Therefore you will need the following tools:

 - Git
 - [Composer](http://getcomposer.org)
 - NPM (coming in 2.0)
 - Docker





## Clone the repository

Create a folder in your development workspace and clone the repository. If you intend to make changes and submit pull requests please Fork us first and create a new branch.

```sh
git clone git@github.com:<your_id>/xibo-cms.git xibo-cms
```



## Install dependencies

Change into your new folder

```sh
cd xibo-cms
```

We recommend installing the dependencies via Docker, so that you are guarenteed consistent dependencies across different development machines.

### PHP dependencies

```bash
docker run --interactive --tty --volume $PWD:/app --volume ~/.composer:/tmp composer install
```

This command also mounts the Composer `/tmp` folder into your home directory so that you can take advantage of Composer caching.

### Website dependencies (webpack)

```bash
docker run -it --volume $PWD:/app --volume ~/.npm:/root/.npm -w /app node:latest sh -c "npm install webpack -g; npm install; npm run build;"
```


## Bring up the Containers

Use Docker Compose to bring up the containers.

```sh
docker-compose up --build -d
```

## Installation Wizard

Visit Xibo in the browser and follow the installation instructions. The CMS will be accessible at `localhost`. When
asked for a database you should select to create a new database and enter these details:

 - Host: `mysql`
 - Admin User: `root`
 - Admin Password: `root`

When asked for a library location you should enter

 - /var/www/cms/library

## Translations
To parse the translations:

```bash
docker-compose exec web sh -c "cd /var/www/cms; php bin/locale.php"
```

```bash
find ./locale ./cache ./lib ./web  -iname "*.php" -print0 | xargs -0 xgettext --from-code=UTF-8 -k_e -k_x -k__ -o locale/default.pot
```

## Swagger API Docs
To generate a `swagger.json` file, with the dev containers running:

```bash
docker-compose exec web sh -c "cd /var/www/cms; vendor/bin/swagger lib -o web/swagger.json"
```

# Application Structure

To find out more about the application code and how everything fits together, please refer to the [advanced section of the manual](https://xibo.org.uk/manual/en/advanced.html).



# Contributing

The standard licence for Xibo is the [AGPLv3](LICENSE). For more information please see [CONTRIBUTING.md](CONTRIBUTING.md).



# Reporting Problems

Support requests can be reported on the [Xibo Community Forum](https://community.xibo.org.uk/c/dev). Verified, re-producable bugs with this repository can be reported in the [Xibo parent repository](https://github.com/xibosignage/xibo/issues).
