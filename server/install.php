<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Alex Harrington
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
DEFINE('XIBO', true);

if (! checkPHP()) 
{
	die('Xibo requires PHP 5.3.3 or later');
}

error_reporting(0);
ini_set('display_errors', 0);

include('lib/app/kit.class.php');
include('install/header.inc');
include('config/config.class.php');
include('config/db_config.php');

// Setup for the Translations using Gettext.
// There is normally a class that will do this for us - but it requires a DB object (which we do not have at install time)
// Would be nice to include a method on the TranslationEngine that did this for us - but without the debugging
// The actual translation function __() is included later in this file.
$langs 		= Kit::GetParam('HTTP_ACCEPT_LANGUAGE', $_SERVER, _STRING);
$lang		= 'en-gb'; 		// Default language
$encoding 	= '';			// We do not seem to need an encoding, but I read somewhere that we might - left as a reminder of this.

if ($langs != '') 
{
	$langs 	= explode(',', $langs);
	$lang 	= $langs[0];
}

// For windows
putenv('LANG='.$lang.'.'.$encoding);
putenv('LANGUAGE='.$lang.'.'.$encoding); 
putenv('LC_ALL='.$lang.'.'.$encoding); 

// Set local
setlocale(LC_ALL, $lang.'.'.$encoding);

// Translations have been setup.

$fault = false;

$xibo_step = Kit::GetParam('xibo_step',_POST,_INT,'0');

if (!isset($xibo_step) || $xibo_step == 0) {
  # First step of the process.
  # Show a welcome screen and next button
  ?>
  <?php echo __("Welcome to the Xibo Installer!"); ?><br /><br />
  <?php echo __("The installer will take you through setting up Xibo one step at a time."); ?><br /><br />
  <?php echo __("Lets get started!"); ?><br /><br />
  <form action="install.php" method="POST">
    <input type="hidden" name="xibo_step" value="1" />
    <div class="loginbutton"><button type="submit"><?php echo __("Next"); ?> ></button></div>
  </form>
  <?php
}
elseif ($xibo_step == 1) {
  # Check environment
  $db = new Database();
  $cObj = new Config();
  ?>
  <p><?php echo __("First we need to check if your server meets Xibo's requirements."); ?></p>
  <div class="checks">
  <?php
    echo $cObj->CheckEnvironment();
    if ($cObj->EnvironmentFault()) {
    ?>
      <form action="install.php" method="POST">
        <input type="hidden" name="xibo_step" value="1" />
        <div class="loginbutton"><button type="submit"><?php echo __("Retest"); ?></button></div>
      </form>
    <?php
    }
    else if ($cObj->EnvironmentWarning()) {
    ?>
      <form action="install.php" method="POST">
        <input type="hidden" name="xibo_step" value="1" />
        <div class="loginbutton"><button type="submit"><?php echo __("Retest"); ?></button></div>
      </form>
      <form action="install.php" method="POST">
        <input type="hidden" name="xibo_step" value="2" />
        <div class="loginbutton"><button type="submit"><?php echo __("Next"); ?> ></button></div>
      </form>
    <?php
    }
    else {
    ?>
      <form action="install.php" method="POST">
        <input type="hidden" name="xibo_step" value="2" />
        <div class="loginbutton"><button type="submit"><?php echo __("Next"); ?> ></button></div>
      </form>
    <?php
    }    
}
elseif ($xibo_step == 2) {
# Create database
## Does database exist already?

  ?>
  <div class="info">
    <p><?php echo __("Xibo needs to setup a new database."); ?></p>
    <p><?php echo __("If you have not yet created an empty database and database user for Xibo to use, and know the username/password of a MySQL administrator, click the \"Create New\" button, otherwise click \"Use Existing\"."); ?></p>
    <p><i><?php echo __("Note that any existing database must be empty"); ?></i></p>
  </div>
  <form action="install.php" method="POST">
    <input type="hidden" name="xibo_step" value="3" />
    <button type="submit"><?php echo __("Create New"); ?></button>
  </form>
  <form action="install.php" method="POST">
    <input type="hidden" name="xibo_step" value="4" />
    <button type="submit"><?php echo __("Use Existing"); ?></button>
  </form>
  <?php
}
elseif ($xibo_step == 3) {
## If not, gather admin password and use to create empty db and new user.
?>
<div class="info">
<p><?php echo __("Since no empty database has been created for Xibo to use, we need the username and password of a MySQL administrator to create a new database, and database user for Xibo."); ?></p>
<p><?php echo __("Additionally, please give us a new username and password to create in MySQL for Xibo to use. Xibo will create this automatically for you."); ?></p>
<form action="install.php" method="POST">
<input type="hidden" name="xibo_step" value="5" />
<input type="hidden" name="db_create" value="On" />
<div class="install_table">
  <p><label for="host"><?php echo __("Host:"); ?>&nbsp;</label><input class="username" type="text" id="host" name="host" size="12" value="localhost" /></p>
  <p><label for="admin_username"><?php echo __("Admin Username:"); ?>&nbsp;</label><input class="username" type="text" id="admin_username" name="admin_username" size="12" /></p>
  <p><label for="admin_password"><?php echo __("Admin Password:"); ?>&nbsp;</label><input class="username" type="password" id="admin_password" name="admin_password" size="12" /></p>
  <p><label for="db_name"><?php echo __("Xibo Database Name:"); ?>&nbsp;</label><input class="username" type="text" id="db_name" name="db_name" size="12" value="xibo" /></p>
  <p><label for="db_username"><?php echo __("Xibo Database Username:"); ?>&nbsp;</label><input class="username" type="text" id="db_username" name="db_username" size="12" value="xibo" /></p>
  <p><label for="db_password"><?php echo __("Xibo Database Password:"); ?>&nbsp;</label><input class="username" type="password" id="db_password" name="db_password" size="12" /></p>
</div>
</div>
<button type="submit"><?php echo __("Create"); ?></button>
</form>
<?php
}
elseif ($xibo_step == 4) {
## Get details of db that's been created already for us
?>
<div class="info">
<p><?php echo __("Please enter the details of the database and user you have created for Xibo."); ?></p>
<form action="install.php" method="POST">
<input type="hidden" name="xibo_step" value="5" />
<input type="hidden" name="db_create" value="Off" />
<div class="install_table">
  <p><label for="host"><?php echo __("Host:"); ?>&nbsp;</label><input class="username" type="text" id="host" name="host" size="12" value="localhost" /></p>
  <p><label for="db_name"><?php echo __("Xibo Database Name:"); ?>&nbsp;</label><input class="username" type="text" id="db_name" name="db_name" size="12" value="xibo" /></p>
  <p><label for="db_username"><?php echo __("Xibo Database Username:"); ?>&nbsp;</label><input class="username" type="text" id="db_username" name="db_username" size="12" value="xibo" /></p>
  <p><label for="db_password"><?php echo __("Xibo Database Password:"); ?>&nbsp;</label><input class="username" type="password" id="db_password" name="db_password" size="12" /></p>
</div>
</div>
<button type="submit"><?php echo __("Create"); ?></button>
</form>
<?php
}
elseif ($xibo_step == 5) {

  $db_create = Kit::GetParam('db_create',_POST,_STRING);

  if (!isset($db_create)) {
    reportError("2",__("Something went wrong"));
  }
  else {
    $db_host = Kit::GetParam('host',_POST,_STRING,'localhost');
    $db_user = Kit::GetParam('db_username',_POST,_PASSWORD);
    $db_pass = Kit::GetParam('db_password',_POST,_PASSWORD);
    $db_name = Kit::GetParam('db_name',_POST,_PASSWORD);
    ?>
    <div class="info">
    <?php
    if ($db_create == 'On') {  
      $db_admin_user = Kit::GetParam('admin_username',_POST,_PASSWORD);
      $db_admin_pass = Kit::GetParam('admin_password',_POST,_PASSWORD);
      
      if (! ($db_host && $db_name && $db_user && $db_admin_user)) {
        # Something was blank.
        # Throw an error.
        reportError("3", __("A field was blank. Please fill in all fields."));
      }
      
      $db = @mysql_connect($db_host,$db_admin_user,$db_admin_pass);
      
      if (! $db) {
        reportError("3", __("Could not connect to MySQL with the administrator details. Please check and try again.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
      }
      
      ?>
      <p><?php echo __("Creating new database."); ?></p>
      <?php
      flush();
      
      $SQL = sprintf("CREATE DATABASE `%s`",
                      mysql_real_escape_string($db_name));
      if (! @mysql_query($SQL, $db)) {
        # Create database and user
        reportError("3", __("Could not create a new database with the administrator details. Please check and try again.") . "<br /><br />" . "MySQL Error:" . "<br />" . mysql_error());
      }
      
      # Choose the MySQL DB to create a user
      @mysql_select_db("mysql", $db);

      # Make $db_host lowercase so it matches "localhost" if required.
      $db_host = strtolower($db_host);
      
      ?>
      <p><?php echo __("Creating new user"); ?></p>
      <?php
      flush();
      
      if ($db_host == 'localhost') {
        $SQL = sprintf("GRANT ALL PRIVILEGES ON `%s`.* to '%s'@'%s' IDENTIFIED BY '%s'",
                        mysql_real_escape_string($db_name),
                        mysql_real_escape_string($db_user),
                        mysql_real_escape_string($db_host),
                        mysql_real_escape_string($db_pass));
      }
      else {
        $SQL = sprintf("GRANT ALL PRIVILEGES ON `%s`.* to '%s'@'%%' IDENTIFIED BY '%s'",
                        mysql_real_escape_string($db_name),
                        mysql_real_escape_string($db_user),
                        mysql_real_escape_string($db_pass));
      }
      if (! @mysql_query($SQL, $db)) {
          reportError("3", __("Could not create a new user with the administrator details. Please check and try again.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
      }
      

      @mysql_query("FLUSH PRIVILEGES", $db);      
      @mysql_close($db);
      
    }
    else {
      if (! ($db_host && $db_name && $db_user && $db_pass)) {
        # Something was blank
        # Throw an error.
        reportError("4", __("A field was blank. Please fill in all fields.") . " " . $db_host . " " . $db_name . " " . $db_user . " " . $db_pass);
      }
    }
    ## Populate database
    
    $db = @mysql_connect($db_host,$db_user,$db_pass);
      
    if (! $db) {
      reportError("4", __("Could not connect to MySQL with the Xibo User account details. Please check and try again.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
    }
      
    @mysql_select_db($db_name,$db);
    
    ?>
    <p>Populating the database</p>
    <?php
    flush();
    
    # Load from sql files to db - HOW?
    $sql_files = array('structure.sql', 'data.sql');

    $sqlStatementCount = 0;

    foreach ($sql_files as $filename) {
      ?>
      <p>Loading from <?php print $filename; ?>
      <?php
        flush();
        
        $delimiter = ';';
        $sql_file = @file_get_contents('install/master/' . $filename);
        $sql_file = remove_remarks($sql_file);
        $sql_file = split_sql_file($sql_file, $delimiter);
    
        foreach ($sql_file as $sql) {
          print ".";
          $sqlStatementCount++;
          flush();
          if (! @mysql_query($sql,$db)) {
            reportError("4", __("An error occured populating the database.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error() . "<br /><br />SQL executed:<br />" . $sql . "<br /><br />Statement number: " . $sqlStatementCount);
          }
        }
        print "</p>";
    }
    @mysql_close($db);
  }
  # Write out a new settings.php
  $fh = fopen("settings.php", 'wt');
  
  if (! $fh) {
    reportError("0", __("Unable to write to settings.php. We already checked this was possible earlier, so something changed."));
  }
  
  settings_strings();
  
  $settings_content = '$dbhost = \'' . $db_host . '\';' . "\n";
  $settings_content .= '$dbuser = \'' . $db_user . '\';' . "\n";
  $settings_content .= '$dbpass = \'' . $db_pass . '\';' . "\n";
  $settings_content .= '$dbname = \'' . $db_name . '\';' . "\n\n";
  $settings_content .= 'define(\'SECRET_KEY\',\'' . gen_secret() . '\');' . "\n";
  
  if (! fwrite($fh, $settings_header . $settings_content . $settings_footer)) {
    reportError("0", __("Unable to write to settings.php. We already checked this was possible earlier, so something changed."));
  }
    
  fclose($fh);
  
  ?>
  </div>
  <div class="install_table">
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="6" />
  </div>
    <button type="submit"><?php echo __("Next"); ?> ></button>
  </form>
  <?php
}
elseif ($xibo_step == 6) {
  # Form to get new admin password
  ?>
  <div class="info">
  <p><?php echo __("Xibo needs to set the \"xibo_admin\" user password. Please enter a password for this account below."); ?></p>
  </div>
  <div class="install_table">
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="7" />
      <p><label for="password1"><?php echo __("Password:"); ?>&nbsp;</label><input type="password" name="password1" size="12" /></p>
      <p><label for="password2"><?php echo __("Retype Password:"); ?>&nbsp;</label><input type="password" name="password2" size="12" /></p>
  </div>
    <button type="submit"><?php echo __("Next"); ?> ></button>
  </form>
  <?php
}
elseif ($xibo_step == 7) {
  # Setup xibo_admin password
  $password1 = Kit::GetParam('password1',_POST,_PASSWORD);
  $password2 = Kit::GetParam('password2',_POST,_PASSWORD);
  
  if (!(($password1 && $password2) && ($password1 == $password2))) {
    reportError("6", __("Please input a new password. Ensure both password fields are identical."));
  }
  
  include('settings.php');
  
  $password_hash = md5($password1);
  
  $db = @mysql_connect($dbhost,$dbuser,$dbpass);
      
    if (! $db) {
      reportError("6", __("Could not connect to MySQL with the Xibo User account details saved in settings.php. Please check and try again.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
    }
      
    @mysql_select_db($dbname,$db);

    $SQL = sprintf("UPDATE `user` SET UserPassword = '%s' WHERE UserID = 1 LIMIT 1",
                    mysql_real_escape_string($password_hash));
    if (! @mysql_query($SQL, $db)) {
      reportError("6", __("An error occured changing the xibo_admin password.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());    
    }
 
    @mysql_close($db);
    
    ?>
    <div class="info">
      <?php echo __("Successfully changed the xibo_admin password. We're nearly there now. Just a couple more steps!"); ?>
    </div>
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="8" />
      <button type="submit"><?php echo __("Next"); ?> ></button>
    </form>
    <?php
}
elseif ($xibo_step == 8) {
  # Configure paths and keys
  ## nuSoap
  ## libraries
  ## server_key
  ?>
  <div class="info">
    <p><b><?php echo __("Library Location"); ?></b></p>
    <p><?php echo __("Xibo needs somewhere to store the things you upload to be shown. Ideally, this should be somewhere outside the root of your webserver - that is such that is not accessible by a web browser. Please input the full path to this folder. If the folder does not already exist, Xibo will attempt to create it for you."); ?></p>
    <form action="install.php" method="POST">
    <div class="install_table">
       <p><label for="library_location"><?php echo __("Library Location:"); ?>&nbsp;</label><input type="text" name="library_location" value="" /></p>
    </div>
    <p><b><?php echo __("Server Key"); ?></b></p>
    <p><?php echo __("Xibo needs you to choose a \"key\". This will be required each time you setup a new client. It should be complicated, and hard to remember. It is visible in the admin interface, so it need not be written down separately."); ?></p>
    <div class="install_table">
      <p><label for="server_key"><?php echo __("Server Key:"); ?> </label><input type="text" name="server_key" value="" /></p>
    </div>
    <p><b><?php echo __("Statistics"); ?></b></p>
    <p><?php echo __("We'd love to know you're running Xibo. If you're happy for us to collect anonymous statistics (version number, number of displays) then please leave the box ticked. Please untick the box if your server does not have direct access to the internet."); ?></p>
    <div class="install_table">
      <p><label for="stats"><?php echo __("Anonymous Statistics:"); ?>&nbsp;</label><input type="checkbox" name="stats" value="true" checked /></p>
    </div>
      <input type="hidden" name="xibo_step" value="9" />
    </div>
      <button type="submit"><?php echo __("Next"); ?> ></button>
    </form>
  <?php
}
elseif ($xibo_step == 9) {

  $server_key = Kit::GetParam('server_key',_POST,_STRING);
  $library_location = Kit::GetParam('library_location',_POST,_STRING);
  $stats = Kit::GetParam('stats',_POST,_BOOL);
  
  // Remove trailing whitespace from the path given.
  $library_location = trim($library_location);

  // Check both fields were completed
  if (! ($server_key && $library_location)) {
    reportError("8", __("A field was blank. Please make sure you complete all fields"));
  }

  if ($stats) {
    $stats="On"; // Fixme: translate ?
  }
  else {
    $stats="Off"; //Fixme : translate ?
  }

  // Does library_location exist already?
  if (! is_dir($library_location)) {
    if (is_file($library_location)) {
      reportError("8", __("A file exists with the name you gave for the Library Location. Please choose another location"));
    }

    // Directory does not exist. Attempt to make it
    // Using mkdir recursively, so it will attempt to make any
    // intermediate folders required.
    if (! mkdir($library_location,0755,true)) {
      reportError("8", __("Could not create the Library Location directory for you. Please ensure the webserver has permission to create a folder in this location, or create the folder manually and grant permission for the webserver to write to the folder."));
    }
    
  }
  
  // Is library_location writable?
  if (! is_writable($library_location)) {
    // Directory is not writable.
    reportError("8", __("The Library Location you gave is not writable by the webserver. Please fix the permissions and try again."));
  }
  
  // Is library_location empty?
  if (count(ls("*",$library_location,true)) > 0) {
    reportError("8", __("The Library Location you gave is not empty. Please give the location of an empty folder"));
  }
  
  // Check if the user has added a trailing slash.
  // If not, add one.
  if (!((substr($library_location, -1) == '/') || (substr($library_location, -1) == '\\'))) {
    $library_location = $library_location . '/';
  }

  include('settings.php');
  
  $db = @mysql_connect($dbhost,$dbuser,$dbpass);
      
    if (! $db) {
      reportError("8", __("Could not connect to MySQL with the Xibo User account details saved in settings.php. Please check and try again.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
    }
      
    @mysql_select_db($dbname,$db);
    
    $SQL = sprintf("UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'LIBRARY_LOCATION' LIMIT 1",
                    mysql_real_escape_string($library_location));
    if (! @mysql_query($SQL, $db)) {
      reportError("8", __("An error occured changing the library location.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());    
    }
    
    $SQL = sprintf("UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'SERVER_KEY' LIMIT 1",
                      mysql_real_escape_string($server_key));
    if (! @mysql_query($SQL, $db)) {
      reportError("8", __("An error occured changing the server key.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());    
    }
    
    $SQL = sprintf("UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'defaultTimezone' LIMIT 1",
                      mysql_real_escape_string(date_default_timezone_get()));
    if (! @mysql_query($SQL, $db)) {
      reportError("8", __("An error occured setting the default timezone.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());    
    }
 
    $SQL = sprintf("UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'PHONE_HOME' LIMIT 1",
                      mysql_real_escape_string($stats));
    if (! @mysql_query($SQL, $db)) {
      reportError("8", __("An error occured setting anonymous statistics.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
    }
    $SQL = "UPDATE `setting` SET `value` = '" . md5(uniqid(rand(), true)) . "' WHERE `setting`.`setting` = 'PHONE_HOME_KEY' LIMIT 1";
    if (! @mysql_query($SQL, $db)) {
      reportError("8", __("An error occured setting anonymous statistics.") . "<br /><br />" . __("MySQL Error:") . "<br />" . mysql_error());
    }
    
    @mysql_close($db);
  
  ?>
  <div class="info">
    <p><?php echo __("Successfully set library location and server key."); ?></p>
  </div>
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="10" />
      <button type="submit"><?php echo __("Next"); ?> ></button>
    </form>
  <?php
}
elseif ($xibo_step == 10) {
# Delete install.php
# Redirect to login page.
  if (! unlink('install.php')) {
    reportError("10", __("Unable to delete install.php. Please ensure the webserver has permission to unlink this file and retry"), __("Retry")); // Fixme : translate "Retry" ?
  }
  if (! unlink('upgrade.php')) {
    reportError("10", __("Unable to delete upgrade.php. Please ensure the webserver has permission to unlink this file and retry"), __("Retry")); // Fixme : translate "Retry" ?
  }
  ?>
  <div class="info">
    <p><b><?php echo __("Xibo was successfully installed."); ?></b></p>
    <p><?php echo __("Please click"); ?>&nbsp;<a href="index.php"><?php echo __("here"); ?></a>&nbsp;<?php echo __("to logon to Xibo as \"xibo_admin\" with the password you chose earlier."); ?></p>
  </div>
  <?php
}
else {
  reportError("0", __("A required parameter was missing. Please go through the installer sequentially!"), __("Start Again")); // Fixme : translate "Start Again" ?
}
 
include('install/footer.inc');

# Functions
function checkFsPermissions() {
  # Check for appropriate filesystem permissions
  return ((is_writable("install.php") && (is_writable("settings.php")) && (is_writable("upgrade.php")) || is_writable(".")));
}

function checkMySQL() {
  # Check PHP has MySQL module installed
  return extension_loaded("mysql");
}

function checkJson() {
  # Check PHP has JSON module installed
  return extension_loaded("json");
}

function checkGd() {
  # Check PHP has JSON module installed
  return extension_loaded("gd");
}

function checkCal() {
  # Check PHP has JSON module installed
  return extension_loaded("calendar");
}
 
function reportError($step, $message, $button_text="&lt; Back") { // fixme : translate ?
?>
    <div class="info">
      <?php print $message; ?>
    </div>
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="<?php print $step; ?>"/>
      <button type="submit"><?php print $button_text; ?></button>
    </form>
  <?php
  include('install/footer.inc');
  die();
} 

// Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
// By Crackster 
/**
 * remove_remarks will strip the sql comment lines out of an uploaded sql file
 */
function remove_remarks($sql){
  $sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^[-].*$/m', "\n", $sql));
  $sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^#.*$/m', "\n", $sql));
  return $sql;
}

// Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
// By Crackster              
/**
 * split_sql_file will split an uploaded sql file into single sql statements.
 * Note: expects trim() to have already been run on $sql.
 */
function split_sql_file($sql, $delimiter){
  $sql = str_replace("\r" , '', $sql);
  $data = preg_split('/' . preg_quote($delimiter, '/') . '$/m', $sql);
  $data = array_map('trim', $data);
  // The empty case
  $end_data = end($data);
  if (empty($end_data))
  {
    unset($data[key($data)]);
  }
  return $data;
}
 
/**
 * This funtion will take a pattern and a folder as the argument and go thru it(recursivly if needed)and return the list of 
 *               all files in that folder.
 * Link             : http://www.bin-co.com/php/scripts/filesystem/ls/
 * License	: BSD
 * Arguments     :  $pattern - The pattern to look out for [OPTIONAL]
 *                    $folder - The path of the directory of which's directory list you want [OPTIONAL]
 *                    $recursivly - The funtion will traverse the folder tree recursivly if this is true. Defaults to false. [OPTIONAL]
 *                    $options - An array of values 'return_files' or 'return_folders' or both
 * Returns       : A flat list with the path of all the files(no folders) that matches the condition given.
 */
function ls($pattern="*", $folder="", $recursivly=false, $options=array('return_files','return_folders')) {
    if($folder) {
        $current_folder = realpath('.');
        if(in_array('quiet', $options)) { // If quiet is on, we will suppress the 'no such folder' error
            if(!file_exists($folder)) return array();
        }
        
        if(!chdir($folder)) return array();
    }
    
    
    $get_files    = in_array('return_files', $options);
    $get_folders= in_array('return_folders', $options);
    $both = array();
    $folders = array();
    
    // Get the all files and folders in the given directory.
    if($get_files) $both = glob($pattern, GLOB_BRACE + GLOB_MARK);
    if($recursivly or $get_folders) $folders = glob("*", GLOB_ONLYDIR + GLOB_MARK);
    
    //If a pattern is specified, make sure even the folders match that pattern.
    $matching_folders = array();
    if($pattern !== '*') $matching_folders = glob($pattern, GLOB_ONLYDIR + GLOB_MARK);
    
    //Get just the files by removing the folders from the list of all files.
    $all = array_values(array_diff($both,$folders));
        
    if($recursivly or $get_folders) {
        foreach ($folders as $this_folder) {
            if($get_folders) {
                //If a pattern is specified, make sure even the folders match that pattern.
                if($pattern !== '*') {
                    if(in_array($this_folder, $matching_folders)) array_push($all, $this_folder);
                }
                else array_push($all, $this_folder);
            }
            
            if($recursivly) {
                // Continue calling this function for all the folders
                $deep_items = ls($pattern, $this_folder, $recursivly, $options); # :RECURSION:
                foreach ($deep_items as $item) {
                    array_push($all, $this_folder . $item);
                }
            }
        }
    }
    
    if($folder) chdir($current_folder);
    return $all;
}

function gen_secret() {
  # Generates a random 12 character alphanumeric string to use as a salt
  mt_srand((double)microtime()*1000000);
  $key = "";
  for ($i=0; $i < 12; $i++) {
    $c = mt_rand(0,2);
    if ($c == 0) {
      $key .= chr(mt_rand(65,90));
    }
    elseif ($c == 1) {
      $key .= chr(mt_rand(97,122));
    }
    else {
      $key .= chr(mt_rand(48,57));
    }
  } 
  
  return $key;
}

function checkPHP() 
{
	return (version_compare("5.3.3",phpversion(), "<="));
}

function CheckGettext() 
{
	return extension_loaded("gettext");
}

// Setup the translations for gettext
function __($string)
{
	if (CheckGettext())
	{
		return _($string);
	}
	else
	{
		return $string;
	}
}

function settings_strings() {
global $settings_header;
global $settings_footer;

  $settings_header = <<<END
<?php

/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo - and is automatically generated by the installer
 *
 * You should not need to edit this file, unless your SQL connection details have changed.
 */

defined('XIBO') or die(__("Sorry, you are not allowed to directly access this page.") . "<br />" . __("Please press the back button in your browser."));

global \$dbhost;
global \$dbuser;
global \$dbpass;
global \$dbname;


END;

$settings_footer = <<<END
?>
END;

  return;
}
?>
