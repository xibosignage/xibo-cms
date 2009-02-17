<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Alex Harrington
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */ 

include('install/header.inc');

if (!isset($_POST['xibo_step'])) {
  # First step of the process.
  # Show a welcome screen and next button
  ?>
  Welcome to the Xibo Installer!<br /><br />
  The installer will take you through setting up Xibo one step at a time.<br /><br />
  Lets get started!<br /><br />
  <form action="install.php" method="POST">
    <input type="hidden" name="xibo_step" value="1" />
    <div class="loginbutton"><button type="submit">Next ></button></div>
  </form>
  <?php
}
elseif ($_POST['xibo_step'] == 1) {
# Check environment
}
## Filesystem Permissions

## PHP 5

## MYSQL

## JSON
 
# Create database

## Does database exist already?

## If not, gather admin password and use to create empty db and new user.

## Populate database

# Setup xibo_admin password

# Configure paths and keys

## nuSoap

## libraries

## server_key

## secret_key

# Delete install.php

# Redirect to login page.
 
include('install/footer.inc');
 
?>