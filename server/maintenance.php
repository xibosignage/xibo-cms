<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-13 Alex Harrington
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
require_once("lib/app/pdoconnect.class.php");
require_once("lib/app/debug.class.php");
require_once("lib/app/kit.class.php");
require_once("lib/data/data.class.php");
require_once("lib/data/display.data.class.php");

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

date_default_timezone_set(Config::GetSetting("defaultTimezone"));

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

// Define the VERSION
Config::Version();

// What is the production mode of the server?
if(Config::GetSetting("SERVER_MODE") == "Test") 
    ini_set('display_errors', 1);

// Debugging?
if(Config::GetSetting("debug") == "On") 
    error_reporting(E_ALL);

// Setup the translations for gettext
TranslationEngine::InitLocale();

// Output HTML Headers
print '<html>';
print '  <head>';
print '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
print '    <title>Open Source Digital Signage - Maintenance</title>';
print '  </head>';
print '<body>';

// Should the Scheduled Task script be running at all?
if(Config::GetSetting("MAINTENANCE_ENABLED")=="Off")
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

    if(Config::GetSetting("MAINTENANCE_ENABLED")=="Protected")
    {
        // Check that the magic parameter is set
        $key = Config::GetSetting("MAINTENANCE_KEY");

        // Get key from POST or from ARGV
        $pKey = Kit::GetParam('key', _GET, _STRING);
        if(isset($argv[1]))
        {
            $aKey = Kit::ValidateParam($argv[1], _STRING);
        }
    }

    if(($aKey == $key) || ($pKey == $key) || (Config::GetSetting("MAINTENANCE_ENABLED")=="On"))
    {
        // Email Alerts
        // Note that email alerts for displays coming back online are triggered directly from
        // the XMDS service.

        print "<h1>" . __("Email Alerts") . "</h1>";
        flush();

        $emailAlerts = Config::GetSetting("MAINTENANCE_EMAIL_ALERTS");
        $alwaysAlert   = Config::GetSetting("MAINTENANCE_ALWAYS_ALERT");

        if ($emailAlerts == "On")
        {
            $emailAlerts = TRUE;
        }
        else
        {
            $emailAlerts = FALSE;
        }
        
        if ($alwaysAlert == "On")
        {
            $alwaysAlert = TRUE;
        }
        else
        {
            $alwaysAlert = FALSE;
        }

        // The time in the past that the last connection must be later than globally.
        $globalTimeout = time() - (60 * Kit::ValidateParam(Config::GetSetting("MAINTENANCE_ALERT_TOUT"),_INT));
        $msgTo         = Kit::ValidateParam(Config::GetSetting("mail_to"),_PASSWORD);
        $msgFrom       = Kit::ValidateParam(Config::GetSetting("mail_from"),_PASSWORD);
            
        // Get a list of all licensed displays
        $SQL = "SELECT `displayid`, `lastaccessed`, `email_alert`, `alert_timeout`, `display`, `loggedin` FROM `display` WHERE licensed = 1";

        if (!$result =$db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to access displays'), E_USER_ERROR);
        }

        // Loop over the licensed displays
        while($row = $db->get_row($result))
        {
            $displayid     = Kit::ValidateParam($row[0],_INT);
            $lastAccessed  = Kit::ValidateParam($row[1],_INT);
            $email_alert   = Kit::ValidateParam($row[2],_INT);
            $alert_timeout = Kit::ValidateParam($row[3],_INT);
            $display_name  = Kit::ValidateParam($row[4],_STRING);
            $loggedin      = Kit::ValidateParam($row[5],_INT);
            $final_timeout = $globalTimeout;
            $last_seen     = date("Y-m-d H:i:s", $lastAccessed);

            if ($alert_timeout != 0)
            {
                $final_timeout = time() - (60 * $alert_timeout);
            }

            if (($final_timeout > $lastAccessed) || ($lastAccessed == ''))
            {
                // Alert
                // Send an email alert if either case is true:
                //   * Email alerts are enabled for this display and we're set to always alert
                //   * Email alerts are enabled for this display and the last time we saw this display it was logged in
                if ($emailAlerts)
                {
                    if ((($email_alert == 1) && $alwaysAlert) || (($loggedin == 1) && ($email_alert == 1)))
                    {
                        $subject  = sprintf(__("Email Alert for Display %s"),$display_name);
                        $body     = sprintf(__("Display %s with ID %d was last seen at %s."),$display_name,$displayid,$last_seen);

                        if (Kit::SendEmail($msgTo, $msgFrom, $subject, $body))
                        {
                            // Successful Alert
                            print "A";
                        }
                        else
                        {
                            // Error sending Alert
                            print "E";
                        }
                    }
                    else
                    {
                        // Alert disabled for this display
                        print "D";
                    }
                }
                else
                {
                    // Email alerts disabled globally
                    print "X";
                }

                // Update the loggedin flag in the database:
                $SQL = sprintf("UPDATE `display` SET `loggedin` = 0 WHERE `displayid` = %d",$displayid);

                if (!$r =$db->query($SQL))
                {
                    trigger_error($db->error());
                    trigger_error(__('Unable to update loggedin status for display.'), E_USER_ERROR);
                }
                
            }            
            else
            {
                print ".";
            }

            flush();
        }

        // Log Tidy
        print "<h1>" . __("Tidy Logs") . "</h1>";
        if(Config::GetSetting("MAINTENANCE_LOG_MAXAGE")!=0)
        {
            $maxage = date("Y-m-d H:i:s",time() - (86400 * Kit::ValidateParam(Config::GetSetting("MAINTENANCE_LOG_MAXAGE"),_INT)));
            
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
        if(Config::GetSetting("MAINTENANCE_STAT_MAXAGE")!=0)
        {
            $maxage = date("Y-m-d H:i:s",time() - (86400 * Kit::ValidateParam(Config::GetSetting("MAINTENANCE_STAT_MAXAGE"),_INT)));
            
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


        // Wake On LAN
        print '<h1>' . __('Wake On LAN') . '</h1>';

        // Create a display object to use later
        $displayObject = new Display($db);

        // Get a list of all displays which have WOL enabled
        $SQL = "SELECT DisplayID, Display, WakeOnLanTime, LastWakeOnLanCommandSent FROM `display` WHERE WakeOnLan = 1";

        foreach($db->GetArray($SQL) as $row)
        {
            $displayId = Kit::ValidateParam($row['DisplayID'], _INT);
            $display = Kit::ValidateParam($row['Display'], _STRING);
            $wakeOnLanTime = Kit::ValidateParam($row['WakeOnLanTime'], _STRING);
            $lastWakeOnLan = Kit::ValidateParam($row['LastWakeOnLanCommandSent'], _INT);

            // Time to WOL (with respect to today)
            $timeToWake = strtotime(date('Y-m-d') . ' ' . $wakeOnLanTime);
            $timeNow = time();

            // Should the display be awake?
            if ($timeNow >= $timeToWake)
            {
                // Client should be awake, so has this displays WOL time been passed
                if ($lastWakeOnLan < $timeToWake)
                {
                    // Call the Wake On Lan method of the display object
                    if (!$displayObject->WakeOnLan($displayId))
                        print $display . ':Error=' . $displayObject->GetErrorMessage() . '<br/>\n';
                    else
                        print $display . ':Sent WOL Message. Previous WOL send time: ' . date('Y-m-d H:i:s', $lastWakeOnLan) . '<br/>\n';
                }
                else
                    print $display . ':Display already awake. Previous WOL send time: ' . date('Y-m-d H:i:s', $lastWakeOnLan) . '<br/>\n';
             }
             else
                print $display . ':Sleeping<br/>\n';
                print $display . ':N/A<br/>\n';
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
