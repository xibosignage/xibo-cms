# Introduction
Xibo - Digital Signage - http://www.xibo.org.uk
Copyright (C) 2006-2016 Daniel Garner and Contributors.

This is the **development branch** and represents the next generation of the Xibo CMS.

## Licence
Xibo is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

Xibo is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with Xibo.  If not, see <http://www.gnu.org/licenses/>.

# Installation

Installing an official release is [described in the manual](http://xibo.org.uk/manual/en/install_cms.html) and in the
official release notes of each release.

# Developing

Xibo uses Vagrant and Docker to ensure all contributers have a repeatable development environment which is easy to get
up and running.

The very same Docker containers are used in our recommended end user installation to promote consistency from development
to deployment.

To these ends this repository includes a `Vagrantfile` to spin up an environment.

## Prerequisites

 - Git
 - [Composer](http://getcomposer.org)
 - Vagrant

## Clone the repository

Create a folder in your development workspace and clone the repository. If you intend to make changes and submit pull
requests please Fork us first and create a new branch.

```
git clone git@github.com:xibosignage/xibo-cms.git xibo-cms
```

## Install dependencies

Change into your new folder

```
cd xibo-cms
```

Install the external dependencies with Composer. Your local machine is unlikely to have the necessary dependencies
to install the packages, hence the `--ignore` switch.

```
php composer.phar install --ignore-platform-reqs
```

## Start Vagrant

```
vagrant up
```

## Installation Wizard

Visit Xibo in the browser and follow the installation instructions. The CMS will be accessible at `localhost`. When
asked for a database you should select to create a new database and enter these details:

 - Host: `mysql`
 - Admin User: `root`
 - Admin Password: `root`

When asked for a library location you should enter

 - /var/www/xibo/library

## Under the hood

Vagrant has created a virtual machine, installed Docker on it and then provisioned 3 Docker containers for Xibo to use.
There is a container for the CMS web server, a container for the mysql database and a container for XMR.

Your cloned repository is mapped into the Vagrant VM under `/data/web` and the Docker container mounts this as
`/var/www/xibo`. Changes you make to the source code on your host machine are immediately reflected in the nested VM
and Docker container.

Database data is maintained in the guest VM and is persisted when the VM is power cycled (`vagrant halt / vagrant up`). For
convenience the Docker MySQL container exposes mysql on port 3306 to the Vagrant VM. You can therefore connect to MySQL
over SSH using `127.0.0.1` and the port/key file shown by `vagrant ssh-config`.


# Application Structure

The Xibo CMS now follows MVC and is PSR-4 compliant.

The folder structure is as follows:

 - /bin - CLI entry point
 - /custom - A location for custom files, such as modules and middleware. Autoloaded as the `\Xibo\Custom` namespace
 - /install - Files related to install/upgrade
 - /lib/Controller - Controllers
 - /lib/Entity - Models
 - /lib/Exception - Exceptions
 - /lib/Factory - Factories for creating Models
 - /lib/Helper - Helper Classes
 - /lib/Middleware - Slim Application Middleware
 - /lib/Storage - Storage Interfaces
 - /lib/Widget - Controllers for Modules
 - /lib/Xmds - Xibo Media Distribution SOAP Service
 - /locale - Translations
 - /modules - Twig Views for Modules and other Module resources
 - /tests - PHPUnit Tests
 - /views - Twig Views
 - /web - Web Document Root
 - /web/index.php - Entry point for the WEB GUI
 - /web/api/index.php - Entry point for the API
 - /web/maintenance/index.php - Entry point for Maintenance
 - /web/modules - Web Serviceable Resources for modules
 - /web/theme/default - Web Portal Default Theme Files
 - /web/theme/compact - Web Portal Compact Menu Theme Override
 - /web/theme/custom - User Themes (overrides files in default theme)
 - /web/xmds.php - XMDS SOAP Service

# Contributing

The standard licence for Xibo is the [AGPLv3](LICENSE). For more information please see [CONTRIBUTING.md](CONTRIBUTING.md).

# Repository

This folder contains the Xibo CMS application.

# Reporting Problems

Support requests can be reported on the [Xibo Community
Forum](https://community.xibo.org.uk/c/dev). Verified, re-producable bugs with this repository can be reported in
the [Xibo parent repository](https://github.com/xibosignage/xibo/issues).
