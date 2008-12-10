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
 
error_reporting(E_ALL);
ini_set('display_errors',1); //we never want to display errors on the screen
require_once("lib/app_functions.php");
// Required Library Files
require_once("lib/debug.class.php");
require_once("lib/kit.class.php");

require_once("config/db_config.php");
require_once("config/config.class.php");

date_default_timezone_set("Europe/London");

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

//parse and init the settings.xml
Config::Load();

//create a DB
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass)) trigger_error($db->error(), E_USER_ERROR);
if (!$db->select_db($dbname)) trigger_error($db->error(), E_USER_ERROR);

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

require_once(Config::GetSetting($db, 'NUSOAP_PATH'));
?>
