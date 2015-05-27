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
require_once("lib/app/app_functions.php");
require_once("lib/app/translationengine.class.php");
require_once("lib/app/pdoconnect.class.php");
require_once("lib/app/debug.class.php");
require_once("lib/app/kit.class.php");
require_once("lib/pages/base.class.php");
require_once("lib/data/data.class.php");
require_once("lib/app/datemanager.class.php");
require_once("lib/app/helpmanager.class.php");
require_once("lib/app/thememanager.class.php");
require_once("lib/data/display.data.class.php");
require_once("lib/modules/module.interface.php");
require_once("lib/modules/module.class.php");
require_once("lib/modules/modulefactory.class.php");
require_once('modules/module_user_general.php');

// Required Config Files
require_once("config/config.class.php");
require_once("config/db_config.php");

/*
 * Before we do anything else, lets check to see if we have a settings.php file
 * Without that file we can deduce that
 *  a) This is a first time install
 *  b) This is a corrupt or failed install
 */
if (!file_exists("settings.php") || file_exists("upgrade.php")) {
	die('Unable to run due to installation issue.');
}

// Define an auto-load function
spl_autoload_register(function ($class) {
    Kit::ClassLoader($class);
});

// parse and init the settings.php
Config::Load();

// Test our DB connection through PDO
try {
    PDOConnect::init();
}
catch (PDOException $e) {
    die('Database connection problem. ' . $e->getMessage());
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
if (Debug::getLevel(Config::GetSetting('audit')) == 10)
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

        $emailAlerts = (Config::GetSetting("MAINTENANCE_EMAIL_ALERTS") == 'On');
        $alwaysAlert = (Config::GetSetting("MAINTENANCE_ALWAYS_ALERT") == 'On');
        $alertForViewUsers = (Config::GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1);
        
        // The time in the past that the last connection must be later than globally.
        $globalTimeout = time() - (60 * Kit::ValidateParam(Config::GetSetting("MAINTENANCE_ALERT_TOUT"), _INT));
        $msgTo = Kit::ValidateParam(Config::GetSetting("mail_to"), _PASSWORD);
        $msgFrom = Kit::ValidateParam(Config::GetSetting("mail_from"), _PASSWORD);

        // We need a theme
        new Theme(new User());

        foreach (Display::ValidateDisplays() as $display) {
            // Is this the first time this display has gone "off-line"
            $displayGoneOffline = (Kit::ValidateParam($display['loggedin'], _INT) == 1);

            // Should we send an email?
            if ($emailAlerts) {
                if (Kit::ValidateParam($display['email_alert'], _INT) == 1) {
                    if ($displayGoneOffline || $alwaysAlert) {
                        // Fields for email
                        $subject = sprintf(__("Email Alert for Display %s"), Kit::ValidateParam($display['display'], _STRING));
                        $body = sprintf(__("Display %s with ID %d was last seen at %s."), 
                            Kit::ValidateParam($display['display'], _STRING), 
                            Kit::ValidateParam($display['displayid'], _INT), 
                            date("Y-m-d H:i:s", Kit::ValidateParam($display['lastaccessed'], _INT)));

                        // Get a list of people that have view access to the display?
                        if ($alertForViewUsers) {
                            foreach (Display::getUsers($display['displayid']) as $user) {
                                if ($user['email'] != '') {
                                    Kit::SendEmail($user['email'], $msgFrom, $subject, $body);
                                }
                            }
                        }

                        if (Kit::SendEmail($msgTo, $msgFrom, $subject, $body)) {
                            // Successful Alert
                            print "A";
                        }
                        else {
                            // Error sending Alert
                            print "E";
                        }
                    }
                }
                else {
                    // Alert disabled for this display
                    print "D";
                }
            }
            else {
                // Email alerts disabled globally
                print "X";
            }
        }

        flush();

        // Log Tidy
        print "<h1>" . __("Tidy Logs") . "</h1>";
        if (Config::GetSetting("MAINTENANCE_LOG_MAXAGE") != 0 && Kit::GetParam('quick', _REQUEST, _INT) != 1) {
            $maxage = date("Y-m-d H:i:s",time() - (86400 * Kit::ValidateParam(Config::GetSetting("MAINTENANCE_LOG_MAXAGE"), _INT)));
            
            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('DELETE FROM `log` WHERE logdate < :maxage');
                $sth->execute(array(
                        'maxage' => $maxage
                    ));

                print __('Done.');
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
            }
        }
        else {
            print "-&gt;" . __("Disabled") . "<br/>\n";
        }

        flush();

        // Stats Tidy
        print "<h1>" . __("Tidy Stats") . "</h1>";
        if (Config::GetSetting("MAINTENANCE_STAT_MAXAGE") != 0 && Kit::GetParam('quick', _REQUEST, _INT) != 1) {
            $maxage = date("Y-m-d H:i:s",time() - (86400 * Kit::ValidateParam(Config::GetSetting("MAINTENANCE_STAT_MAXAGE"),_INT)));
            
            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('DELETE FROM `stat` WHERE statDate < :maxage');
                $sth->execute(array(
                        'maxage' => $maxage
                    ));

                print __('Done.');
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
            }
        }
        else {
            print "-&gt;" . __("Disabled") . "<br/>\n";
        }

        flush();

        // Validate Display Licence Slots
        $maxDisplays = Config::GetSetting('MAX_LICENSED_DISPLAYS');

        if ($maxDisplays > 0) {
            print '<h1>' . __('Licence Slot Validation') . '</h1>';

            // Get a list of all displays
            try {
                $dbh = PDOConnect::init();
                $sth = $dbh->prepare('SELECT displayId, display FROM `display` WHERE licensed = 1 ORDER BY lastAccessed');
                $sth->execute();

                $displays = $sth->fetchAll(PDO::FETCH_ASSOC);

                if (count($displays) > $maxDisplays) {
                    // :(
                    // We need to un-licence some displays
                    $difference = count($displays) - $maxDisplays;

                    $update = $dbh->prepare('UPDATE `display` SET licensed = 0 WHERE displayId = :displayId');

                    foreach ($displays as $display) {

                        // If we are down to 0 difference, then stop
                        if ($difference == 0)
                            break;

                        echo sprintf(__('Disabling %s'), $display['display']) . '<br/>' . PHP_EOL;
                        $update->execute(array('displayId' => $display['displayId']));

                        $difference--;
                    }
                }
                else {
                    echo __('Done.');
                }
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
            }

            flush();
        }

        // Wake On LAN
        print '<h1>' . __('Wake On LAN') . '</h1>';

        // Create a display object to use later
        $displayObject = new Display();

        try {
            $dbh = PDOConnect::init();
        
            // Get a list of all displays which have WOL enabled
            $sth = $dbh->prepare('SELECT DisplayID, Display, WakeOnLanTime, LastWakeOnLanCommandSent FROM `display` WHERE WakeOnLan = 1');
            $sth->execute(array());

            foreach($sth->fetchAll() as $row) {
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

            print __('Done.');
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
        }

        flush();

        // Keep tidy
        Media::removeExpiredFiles();

        // Install module files
        if (Kit::GetParam('quick', _REQUEST, _INT) != 1) {
            Media::installAllModuleFiles();
        }
    }
    else {
        print __("Maintenance key invalid.");
    }
}
// Output HTML Footers
print "\n  </body>\n";
print "</html>";
?>
