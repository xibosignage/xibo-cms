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

  ?>
  <div class="info">
    <p>Xibo needs to setup a new database.</p>
    <p>If you have not yet created an empty database and database user for
    Xibo to use, and know the username/password of a MySQL administrator,
    click the "Create New" button, otherwise click "Use Existing".</p>
    <p><i>Note that any existing database must be empty</i></p>
  </div>
  <form action="install.php" method="POST">
    <input type="hidden" name="xibo_step" value="3">
    <button type="submit">Create New</button>
  </form>
  <form action="install.php" method="POST">
    <input type="hidden" name="xibo_step" value="4">
    <button type="submit">Use Existing</button>
  </form>
  <?php
}
elseif ($_POST['xibo_step'] == 3) {
## If not, gather admin password and use to create empty db and new user.
?>
<div class="info">
<p>Since no empty database has been created for Xibo to use, we need the username
and password of a MySQL administrator to create a new database, and database
user for Xibo.</p>
<p>Additionally, please give us a new username and password to create in MySQL
for Xibo to use. Xibo will create this automatically for you.</p>
<form action="install.php" method="POST">
<input type="hidden" name="xibo_step" value="5">
<input type="hidden" name="db_create" value="true">
<div class="install_table">
  <p><label for="host">Host: </label><input class="username" type="text" id="host" name="host" size="12" value="localhost" /></p>
  <p><label for="admin_username">Admin Username: </label><input class="username" type="text" id="admin_username" name="admin_username" size="12" /></p>
  <p><label for="admin_password">Admin Password: </label><input class="username" type="password" id="admin_password" name="admin_password" size="12" /></p>
  <p><label for="db_name">Xibo Database Name: </label><input class="username" type="text" id="db_name" name="db_name" size="12" value="xibo" /></p>
  <p><label for="db_username">Xibo Database Username: </label><input class="username" type="text" id="db_username" name="db_username" size="12" value="xibo" /></p>
  <p><label for="db_password">Xibo Database Password: </label><input class="username" type="password" id="db_password" name="db_password" size="12" /></p>
</div>
</div>
<button type="submit">Create</button>
</form>
<?php
}
elseif ($_POST['xibo_step'] == 4) {
## Get details of db that's been created already for us
?>
<div class="info">
<p>Please enter the details of the database and user you have
created for Xibo.</p>
<form action="install.php" method="POST">
<input type="hidden" name="xibo_step" value="5">
<input type="hidden" name="db_create" value="false">
<div class="install_table">
  <p><label for="host">Host: </label><input class="username" type="text" id="host" name="host" size="12" value="localhost" /></p>
  <p><label for="db_name">Xibo Database Name: </label><input class="username" type="text" id="db_name" name="db_name" size="12" value="xibo" /></p>
  <p><label for="db_username">Xibo Database Username: </label><input class="username" type="text" id="db_username" name="db_username" size="12" value="xibo" /></p>
  <p><label for="db_password">Xibo Database Password: </label><input class="username" type="password" id="db_password" name="db_password" size="12" /></p>
</div>
</div>
<button type="submit">Create</button>
</form>
<?php
}
elseif ($_POST['xibo_step'] == 5) {
  if (!isset($_POST['db_create'])) {
    reportError("2","Something went wrong");
  }
  else {
    $db_host = $_POST['host'];
    $db_user = $_POST['db_username'];
    $db_pass = $_POST['db_password'];
    $db_name = $_POST['db_name'];
    
    if ($_POST['db_create'] == "true") {  
      $db_admin_user = $_POST['admin_username'];
      $db_admin_pass = $_POST['admin_password'];
      
      if (! ($db_host && $db_name && $db_user && $db_pass && $db_admin_user && $db_admin_pass)) {
        # Something was blank.
        # Throw an error.
        reportError("3", "A field was blank. Please fill in all fields.");
      }
      
      $db = @mysql_connect($db_host,$db_admin_user,$db_admin_pass);
      
      if (! $db) {
        reportError("3", "Could not connect to MySQL with the administrator details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
      }
      
      if (! @mysql_create_db($db_name, $db)) {
        # Create database and user
        reportError("3", "Could not create a new database with the administrator details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
      }
      
      # Choose the MySQL DB to create a user
      @mysql_select_db("mysql", $db);
      
      if (! @mysql_query("GRANT ALL PRIVILEGES ON " . $db_name . " to '" . $db_user . "'@'%' IDENTIFIED BY '" . $db_pass . "'", $db)) {
        reportError("3", "Could not create a new user with the administrator details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
      }
      
      @mysql_close($db);
      
    }
    else {
      if (! ($db_host && $db_name && $db_user && $db_pass)) {
        # Something was blank
        # Throw an error.
        reportError("4", "A field was blank. Please fill in all fields.");
      }
    }
    ## Populate database
    
    $db = @mysql_connect($db_host,$db_user,$db_pass);
      
    if (! $db) {
      reportError("4", "Could not connect to MySQL with the Xibo User account details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
    }
      
    @mysql_select_db($db_name,$db);
    
    # Load from sql files to db - HOW? //TODO
  }
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
 
function reportError($step, $message) {
?>
    <div class="info">
      <?php print $message; ?>
    </div>
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="<?php print $step; ?>">
      <button type="submit">&lt; Back</button>
    </form>
  <?php
  include('install/footer.inc');
  die();
} 
 
?>