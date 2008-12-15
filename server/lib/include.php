<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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

// No errors reported until we read the settings from the DB
error_reporting(0);
ini_set('display_errors', 0);
ini_set('gd.jpeg_ignore_warning', 1);

// Required Library Files
require_once("lib/debug.class.php");
require_once("lib/kit.class.php");
require_once("lib/app_functions.php");

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
	show_no_settings();	
}
require_once("config/config.class.php");

// parse and init the settings.php
Config::Load();

// create a database class instance
require_once("config/db_config.php");

$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass)) trigger_error($db->error(), E_USER_ERROR);
if (!$db->select_db($dbname)) trigger_error($db->error(), E_USER_ERROR);

date_default_timezone_set(Config::GetSetting($db, "defaultTimezone"));

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

// Define the VERSION
Config::Version($db);

// Debugging?
if(Config::GetSetting($db,"debug")=="On") 
{
	error_reporting(E_ALL);
}

// Page variable set? Otherwise default to index
$page = Kit::GetParam('p', _REQUEST, _WORD, 'index');

// Create login control system
$module = Config::GetSetting($db, "userModule");

require_once("modules/$module");
$user = new user();

/*
 * Require a class for the page we have loaded
 * Unless it has already been loaded for us (i.e a user page)
 */
$pageObject = $page."DAO";

if(!class_exists($pageObject)) 
{
	require("lib/app/$page.class.php");
}

// Session handling
include_once("lib/session.class.php");
$session = new Session($db);

Session::Set('pagename', $page); //Assign the page name to the session

$session->set_page(session_id(), $page);

// Vars
$ajax			= Kit::GetParam('ajax', _REQUEST, _BOOL, false);
$functionstring = Kit::GetParam('q', _REQUEST, _WORD);
$userid 		= Kit::GetParam('userid', _SESSION, _INT);

// If we have an AJAX request, include the AJAX request handler
if ($ajax) 
{
	require_once("lib/ajax_request_handler.class.php");
}

if ($functionstring != '') 
{

	if ($functionstring != "login" && $functionstring != "forgotten") 
	{ 
		//check auth info unless we are logging in
		$user->attempt_login($ajax); //the most common query (q) is from an AJAX call - either a form request, or submit
		
		//permissions
		$g_Security = groupPageSecurity($user->getGroupFromID($userid, true));
		
		pageSecurityCheck($page);
	}

	$pageObject = new $pageObject($db);
	
	if (method_exists($pageObject,$functionstring)) 
	{
		$reloadLocation = $pageObject->$functionstring();
	}
	else 
	{
		trigger_error("Unrecognised text in action variable $functionstring", E_USER_ERROR);
	}

    // once we have dealt with it, reload the page    
    if($reloadLocation) 
	{    	
    	Kit::Redirect($reloadLocation);
    }
}
// Display a page instead
else 
{
	// create a user object (will try to login)
	// we must do this after executing any functions otherwise we will be logged
	// out again before exec any log in function calls
	$user->attempt_login(false); //we expect AJAX to be false here
	
	// permissions
	$g_Security = groupPageSecurity($user->getGroupFromID($userid, true));
	
	pageSecurityCheck($page);
	
	// output the page
	$pageObject = new $pageObject($db);

	include("template/header.php");
	$pageObject->displayPage();
	include("template/footer.php");
}
?>