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

$fault = false;

if (!isset($_POST['xibo_step']) || $_POST['xibo_step'] == 0) {
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
  ?>
  <p>First we need to check if your server meets Xibo's requirements.</p>
  <div class="checks">
  <?php
## Filesystem Permissions
    if (checkFsPermissions()) {
    ?>
      <img src="install/dot_green.gif"> Filesystem Permissions<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> Filesystem Permissions</br>
      <div class="check_explain">
      Xibo needs to be able to write to the following
      <ul>
        <li> settings.php
        <li> install.php
      </ul>
      Please fix this, and retest.<br />
      </div>
    <?php
    }
## PHP5
    if (checkPHP()) {
    ?>
      <img src="install/dot_green.gif"> PHP Version<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP Version<br />
      <div class="check_explain">
      Xibo requires PHP version 5 or later.<br />
      Please fix this, and retest.<br />
      </div>
    <?php
    }
## MYSQL
  if (checkMySQL()) {
    ?>
      <img src="install/dot_green.gif"> PHP MySQL Extension<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP MySQL Extension<br />
      <div class="check_explain">
      Xibo needs to access a MySQL database to function.<br />
      Please install MySQL and the appropriate MySQL extension and retest.<br />
      </div>
    <?php
    }
## JSON
  if (checkJson()) {
    ?>
      <img src="install/dot_green.gif"> PHP JSON Extension<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP JSON Extension<br />
      <div class="check_explain">
      Xibo needs the PHP JSON extension to function.<br />
      Please install the PHP JSON extension and retest.<br />
      </div>
    <?php
    }
    ?>
    <br /><br />
    </div>
    <?php
    if ($fault) {
    ?>
      <form action="install.php" method="POST">
        <input type="hidden" name="xibo_step" value="1">
        <div class="loginbutton"><button type="submit">Retest</button></div>
      </form>
    <?php
    }
    else {
    ?>
      <form action="install.php" method="POST">
        <input type="hidden" name="xibo_step" value="2">
        <div class="loginbutton"><button type="submit">Next ></button></div>
      </form>
    <?php
    }    
}
elseif ($_POST['xibo_step'] == 2) {
# Create database
## Does database exist already?
## If not, gather admin password and use to create empty db and new user.
## Populate database
}
# Setup xibo_admin password

# Configure paths and keys

## nuSoap

## libraries

## server_key

## secret_key

# Delete install.php

# Redirect to login page.
 
include('install/footer.inc');

# Functions

function checkFsPermissions() {
  # Check for appropriate filesystem permissions
  return (is_writable("install.php") && (is_writable("settings.php") || is_writable(".")));
}

function checkPHP() {
  # Check PHP version > 5
  return (version_compare("5",phpversion(), "<="));
}

function checkMySQL() {
  # Check PHP has MySQL module installed
  return extension_loaded("mysql");
}

function checkJson() {
  # Check PHP has JSON module installed
  return extension_loaded("json");
}
 
?>