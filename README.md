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
A VagrantFile is included to ease set up and configuration of a development environment. The VagrantFile expects a "library" folder to exist one level up the folder tree to be used as the location of the CMS library.