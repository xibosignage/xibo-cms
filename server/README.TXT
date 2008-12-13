Xibo - Digitial Signage - http://www.xibo.org.uk
Copyright (C) 2006,2007,2008 Daniel Garner and James Packer

This file is part of Xibo.

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


This folder contains the Xibo SERVER application.

Requirements:

PHP 5+
 - SimpleXML support
 - JSON Support (not currently required but will be)

MySQL

Libraries
 - nuSOAP (not included)
 - FCKEditor (included)
 - jQuery (included)
 
Installation:

 - Create a DB and Run Database.sql
 - Edit the settings.php file and fill in your DB connection information
 - Log in using
 	u: xibo_admin
 	p: admin
 - Go to "Admin -> Settings" and set the path for the nuSOAP installation