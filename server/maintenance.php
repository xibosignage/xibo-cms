<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Alex Harrington
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

define('SCHEDULEDTASK', true);
define('XIBO', true);

// Supress errors until we read settings from the database
error_reporting(0);
ini_set('display_errors', 0);
ini_set('gd.jpeg_ignore_warning', 1);

// Required Library Files
require_once("lib/app/translationengine.class.php");
require_once("lib/app/debug.class.php");
require_once("lib/app/kit.class.php");

// Required Config Files
require_once("config/config.class.php");
require_once("config/db_config.php");

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

// create a database class instance
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass))
{
    die('Xibo has a database connection problem.');
}

if (!$db->select_db($dbname))
{
    die('Xibo has a database connection problem.');
}

date_default_timezone_set(Config::GetSetting($db, "defaultTimezone"));

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

// Define the VERSION
Config::Version($db);

// What is the production mode of the server?
if(Config::GetSetting($db, "SERVER_MODE")=="Test") ini_set('display_errors', 1);

// Debugging?
if(Config::GetSetting($db, "debug")=="On") error_reporting(E_ALL);

// Setup the translations for gettext
TranslationEngine::InitLocale($db);

// Output HTML Headers
print '<html>';
print '  <head>';
print '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
print '    <title>Xibo Open Source Digital Signage - Maintenance</title>';
print '  </head>';
print '<body>';
print '  <!-- Copyright 2010 - Alex Harrington, part of Xibo Open Source Digital Signage -->';

// Should the Scheduled Task script be running at all?
if(Config::GetSetting($db, "MAINTENANCE_ENABLED")=="Off")
{
    print "<h1>" . __("Maintenance Disabled") . "</h1>";
    print __("Maintenance tasks are disabled at the moment. Please enable them in the &quot;Settings&quot; dialog.");
}
else
{
    // Set defaults that don't match on purpose!
    $key = 1;
    $aKey = 2;
    $pKey = 3;

    if(Config::GetSetting($db, "MAINTENANCE_ENABLED")=="Protected")
    {
        // Check that the magic parameter is set
        $key = Config::GetSetting($db, "MAINTENANCE_KEY");

        // Get key from POST or from ARGV
        $pKey = Kit::GetParam('key', _GET, _STRING);
        $aKey = Kit::ValidateParam($argv[1], _STRING);

    }

    if(($aKey == $key) || ($pKey == $key) || (Config::GetSetting($db, "MAINTENANCE_ENABLED")=="On"))
    {
        // Email Alerts
        print "<h1>" . __("Email Alerts") . "</h1>";
        flush();
        if(Config::GetSetting($db, "MAINTENANCE_EMAIL_ALERTS")=="On")
        {
            // The time in the past that the last connection must be later than globally.
            $globalTimeout = time() - (60 * Kit::ValidateParam(Config::GetSetting($db, "MAINTENANCE_ALERT_TOUT"),_INT));
            $msgTo         = Kit::ValidateParam(Config::GetSetting($db, "mail_to"),_PASSWORD);
            $msgFrom       = Kit::ValidateParam(Config::GetSetting($db, "mail_from"),_PASSWORD);
            
            // Get a list of all licensed displays
            $SQL = "SELECT `displayid`, `lastaccessed`, `email_alert`, `alert_timeout`, `display` FROM `display` WHERE licensed = 1";

            if (!$result =$db->query($SQL))
            {
            	trigger_error($db->error());
            	trigger_error(__('Unable to access displays'), E_USER_ERROR);
            }

            while($row = $db->get_row($result))
            {
                $displayid     = Kit::ValidateParam($row[0],_INT);
                $lastAccessed  = Kit::ValidateParam($row[1],_INT);
                $email_alert   = Kit::ValidateParam($row[2],_INT);
                $alert_timeout = Kit::ValidateParam($row[3],_INT);
                $display_name  = Kit::ValidateParam($row[4],_STRING);
                $final_timeout = $globalTimeout;
                $last_seen     = date("Y-m-d H:i:s", $lastAccessed);

                // print $final_timeout . "|" . $lastAccessed;

                if ($alert_timeout != 0)
                {
                    $final_timeout = time() - (60 * $alert_timeout);
                }

                if (($final_timeout > $lastAccessed) || ($lastAccessed == ''))
                {
                    // Alert
                    if ($email_alert == 1)
                    {
                        $subject  = sprintf(__("Xibo Email Alert for Display %s"),$display_name);
                        $body     = sprintf(__("Display %s with ID %d was last seen at %s."),$display_name,$displayid,$last_seen);
                        $headers  = sprintf("From: %s\r\nX-Mailer: php", $msgFrom);

                        if (mail($msgTo, $subject, $body, $headers))
                        {
                            print "A";
                        }
                        else
                        {
                            print "E";
                        }
                    }
                    else
                    {
                        print "X";
                    }
                }
                else
                {
                    print ".";
                }
                flush();
            }
        }
        else
        {
            print "-&gt;" . __("Disabled") . "<br/>\n";
        }

        // Log Tidy
        print "<h1>" . __("Tidy Logs") . "</h1>";
        if(Config::GetSetting($db, "MAINTENANCE_LOG_MAXAGE")!=0)
        {
            $maxage = date("Y-m-d H:i:s",time() - 86400 * Kit::ValidateParam(Config::GetSetting($db, "MAINTENTANCE_LOG_MAXAGE")));
            
            $SQL = sprintf("DELETE from `log` WHERE logdate < '%s'",$maxage);
            if ((!$db->query($SQL)))
            {
                trigger_error($db->error());
            }
            else
            {
                print __("Done.");
            }
        }
        else
        {
            print "-&gt;" . __("Disabled") . "<br/>\n";
        }
        flush();

        // Stats Tidy
        print "<h1>" . __("Tidy Stats") . "</h1>";
        if(Config::GetSetting($db, "MAINTENANCE_STAT_MAXAGE")!=0)
        {
            $maxage = date("Y-m-d H:i:s",time() - 86400 * Kit::ValidateParam(Config::GetSetting($db, "MAINTENTANCE_STAT_MAXAGE")));
            
            $SQL = sprintf("DELETE from `stat` WHERE statDate < '%s'",$maxage);
            if ((!$db->query($SQL)))
            {
                trigger_error($db->error());
            }
            else
            {
                print __("Done.");
            }
        }
        else
        {
            print "-&gt;" . __("Disabled") . "<br/>\n";
        }
        flush();
    }
    else
    {
        print __("Maintenance key invalid.");
    }
}
// Output HTML Footers
print "\n  </body>\n";
print "</html>";
?>
