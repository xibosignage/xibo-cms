<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner and James Packer
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

define('WEBSITE_VERSION', 70);

// No errors reported until we read the settings from the DB
error_reporting(0);
ini_set('display_errors', 0);
ini_set('gd.jpeg_ignore_warning', 1);

// Required Library Files
require_once("lib/app/pdoconnect.class.php");
require_once("lib/app/translationengine.class.php");
require_once("lib/app/debug.class.php");
require_once("lib/app/kit.class.php");
require_once("lib/app/pagemanager.class.php");
require_once("lib/app/menumanager.class.php");
require_once("lib/app/modulemanager.class.php");
require_once("lib/app/permissionmanager.class.php");
require_once("lib/app/formmanager.class.php");
require_once("lib/app/helpmanager.class.php");
require_once("lib/app/responsemanager.class.php");
require_once("lib/app/datemanager.class.php");
require_once("lib/app/app_functions.php");
require_once("lib/modules/module.interface.php");
require_once("lib/modules/module.class.php");
require_once("lib/data/data.class.php");
require_once("lib/app/session.class.php");
require_once("lib/app/thememanager.class.php");

// Required Config Files
require_once("config/config.class.php");
require_once("config/db_config.php");

// Sort out Magic Quotes
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

/*
 * Before we do anything else, lets check to see if we have a settings.php file
 * Without that file we can deduce that
 *  a) This is a first time install
 *  b) This is a corrupt or failed install
 */
if (!file_exists("settings.php")) 
{
	Kit::Redirect("install.php");
	die();
}

if (file_exists("upgrade.php"))
{
    Kit::Redirect("upgrade.php");
    die();
}

// parse and init the settings.php
Config::Load();

// Test our DB connection through PDO
try {
    PDOConnect::init();
}
catch (PDOException $e) {
    die('Database connection problem. ' . $e->getMessage());
}

// create a database class instance (legacy)
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass))
{
    die('Database connection problem.');
}

if (!$db->select_db($dbname))
{
    die('Database connection problem.');
}

date_default_timezone_set(Config::GetSetting("defaultTimezone"));

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

// Define the VERSION
Config::Version();

// Does the version in the DB match the version of the code?
if (DBVERSION != WEBSITE_VERSION)
    die(sprintf('Incompatible database version detected. Please ensure your database and website versions match. You have database %d and website %d', DBVERSION, WEBSITE_VERSION));

// What is the production mode of the server?
if(Config::GetSetting('SERVER_MODE') == 'Test') 
    ini_set('display_errors', 1);

// Debugging?
if(Config::GetSetting("debug") == "On") 
    error_reporting(E_ALL);

// Setup the translations for gettext
TranslationEngine::InitLocale();

// Create login control system
require_once('modules/' . Config::GetSetting("userModule"));

// Create a Session
$session = new Session();

// Work out the location of this service
$serviceLocation = Kit::GetXiboRoot();

// OAuth
require_once('lib/oauth.inc.php');

// Page variable set? Otherwise default to index
$page = Kit::GetParam('p', _REQUEST, _WORD, 'index');

// Assign the page name to the session
$session->set_page(session_id(), $page);

// Create a user
$user = new User($db);

// Create Page
try {
    $pageManager = new PageManager($db, $user, $page);
    $pageManager->Authenticate();
    $pageManager->Render();    
}
catch (Exception $e) {
    print $e->getMessage();
}

die();
?>
