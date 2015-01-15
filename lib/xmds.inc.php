<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
 
error_reporting(0);
ini_set('display_errors', 0); //we never want to display errors on the screen

// Required Library Files
require_once("lib/app/pdoconnect.class.php");
require_once('lib/app/translationengine.class.php');
require_once('lib/app/app_functions.php');
require_once('lib/app/debug.class.php');
require_once('lib/app/kit.class.php');
require_once('lib/app/permissionmanager.class.php');
require_once("lib/app/responsemanager.class.php");
require_once('lib/data/data.class.php');
require_once('config/db_config.php');
require_once('config/config.class.php');
include_once('lib/data/stat.data.class.php');
require_once('lib/data/data.class.php');
require_once('lib/data/display.data.class.php');
require_once('lib/service/serviceresponse.class.php');
require_once('modules/module_user_general.php');
require_once('lib/modules/module.interface.php');
require_once('lib/modules/module.class.php');


// Sort out magic quotes
if (get_magic_quotes_gpc()) 
{
    function stripslashes_deep($value)
    {
        $value = is_array($value) ?
                    array_map('stripslashes_deep', $value) :
                    stripslashes($value);

        return $value;
    }

    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}

if (!file_exists("settings.php")) {
  // Xibo has not been configured. Just quit since we can't
  // raise a SOAP error because we don't know where
  // nuSOAP is yet.
  die("Not configured. Visit " . Kit::GetURL() . " to configure.");
}

if (file_exists("upgrade.php")) {
  // An upgrade is in progress. Just quit since the server
  // won't be in a servicable state
  die("An upgrade is pending. Visit " . Kit::GetURL() . ".");
}

//parse and init the settings.xml
Config::Load();

//create a DB
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass)) trigger_error($db->error(), E_USER_ERROR);
if (!$db->select_db($dbname)) trigger_error($db->error(), E_USER_ERROR);

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

date_default_timezone_set(Config::GetSetting('defaultTimezone'));

// Work out the location of this service
$serviceLocation = Kit::GetXiboRoot();

// OAuth
require_once('lib/oauth.inc.php');

// Setup the translations for gettext
TranslationEngine::InitLocale();
?>
