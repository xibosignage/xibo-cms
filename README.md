# Introduction
Xibo - Digital Signage - http://www.xibo.org.uk
Copyright (C) 2006-2018 Spring Signage Ltd and Contributors.

This is the **development branch** and represents the next generation of the Xibo CMS.

## Licence
Xibo is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or
any later version.

Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with Xibo.  If not, see <http://www.gnu.org/licenses/>.



# Installation

Installing an official release is [described in the manual](http://xibo.org.uk/manual/en/install_cms.html) and in the official release notes of each release.



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

Install the external dependencies with Composer. Your local machine is unlikely to have the necessary dependencies to install the packages, hence the `--ignore` switch.

```sh
php composer.phar install --ignore-platform-reqs
```



## Bring up the Containers

Use Docker Compose to bring up the containers.

```sh
docker-compose up --build -d
```

This will create a model installation with a DB container holding the `cms` database, mapped to external port 3315, a XMR container and a WEB container which maps the working directory into `/var/www/cms`, which is inturn served by Apache.

Editing files in your favourite editor on your host file system will cause them to be updated inside the web container.

Your database is persisted in `/containers/db` and will survive reboots, etc.




# Application Structure

To find out more about the application code and how everything fits together, please refer to the [advanced section of the manual](https://xibo.org.uk/manual/en/advanced.html).



# Contributing

The standard licence for Xibo is the [AGPLv3](LICENSE). For more information please see [CONTRIBUTING.md](CONTRIBUTING.md).



# Reporting Problems

Support requests can be reported on the [Xibo Community Forum](https://community.xibo.org.uk/c/dev). Verified, re-producable bugs with this repository can be reported in the [Xibo parent repository](https://github.com/xibosignage/xibo/issues).
