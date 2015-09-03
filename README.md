# Introduction
Xibo - Digital Signage - http://www.xibo.org.uk
Copyright (C) 2006-2015 Daniel Garner and Contributors.

This is the **1.8.0-alpha development branch** and represents the next generation of the Xibo CMS.

At present you cannot upgrade an earlier version to this release.

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

# Installation from the Repository
The Xibo CMS can be installed from the repository using Composer.

Navigate to the folder where you want to install the CMS, ideally below your web root:

```
cd /var
```

Then clone the repository:

```
git clone git@github.com:xibosignage/xibo-cms.git xibo-cms
```

Install the external dependencies with Composer (visit [getcomposer.org](http://getcomposer.org) for instructions):

```
php composer.phar install
```

## Web Server Configuration
It is highly recommended to use Xibo with URL re-writing enabled. A `.htaccess` file has been provided in `web/.htaccess`.
This file assumes that Xibo is being served from the web server document root or from a virtual host.

If an alias is required then the `.htaccess` file will need to be modified to include a `RewriteBase` directive that matches
the alias.

For example, if the alias is `/xibo` the `.htaccess` should have: `RewriteBase /xibo`.

## Installation Wizard
Visit Xibo in the browser and follow the installation instructions.


# Development
The Xibo CMS now follows MVC and is PSR-4 compliant.

The folder structure is as follows:
 
 - install - Files related to install/upgrade
 - lib/Controller - Controllers
 - lib/Entity - Models
 - lib/Exception - Exceptions
 - lib/Factory - Factories for creating Models
 - lib/Helper - Helper Classes
 - lib/Middleware - Slim Application Middleware
 - lib/Storage - Storage Interfaces
 - lib/Widget - Controllers for Modules
 - lib/Xmds - Xibo Media Distribution SOAP Service
 - locale - Translations
 - modules/ - Twig Views for Modules and other Module resources
 - tests/ - PHPUnit Tests
 - views - Twig Views
 - web/ - Web Document Root
 - web/index.php - Entry point for the WEB GUI
 - web/api/index.php - Entry point for the API
 - web/maintenance/index.php - Entry point for Maintenance
 - web/modules - Web Serviceable Resources for modules
 - web/theme - GUI theme files
 - web/xmds.php - XMDS SOAP Service

# Contributing
The standard licence for Xibo is the [AGPLv3](LICENSE). For more information please see [CONTRIBUTING.md](CONTRIBUTING.md).

# Repository
This folder contains the Xibo CMS application.

# Vagrant
A VagrantFile is included to ease set up and configuration of a development environment. After `vagrant up` completes 
it is necessary to ssh to the box and adjust the `DocumentRoot` of the `000-default.conf` virtual host to be `/var/www/web`. 