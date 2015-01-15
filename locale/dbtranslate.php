<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-12 Daniel Garner
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

/* Translations from the Database that need to be registered with Gettext */

echo __('Schedule');
echo __('Layout');
echo __('Library');
echo __('Administration');
echo __('Advanced');
echo __('Media');
echo __('Displays');
echo __('Groups');
echo __('Users');
echo __('Log');
echo __('License');
echo __('Sessions');
echo __('Settings');
echo __('Schedule');
echo __('Layouts');
echo __('Library');
echo __('Templates');
echo __('Settings');
echo __('Edit your Display');
echo __('Report Fault');
echo __('Statistics');
echo __('Display Groups');
echo __('Content');
echo __('Default');
echo __('Error');
echo __('General');
echo __('Path');
echo __('DataSets');
echo __('Modules');
echo __('Campaigns');
echo __('Transitions');
echo __('Resolutions');
echo __('User Groups');
echo __('Help Links');

// Settings translations
echo __('jpg_length');
echo __('ppt_length');
echo __('swf_length');
echo __('defaultMedia');
echo __('defaultPlaylist');
echo __('defaultUsertype');
echo __('defaultTimezone');
echo __('debug');
echo __('audit');
echo __('SERVER_MODE');
echo __('SERVER_KEY');
echo __('PHONE_HOME');
echo __('LIBRARY_LOCATION');
echo __('MAINTENANCE_ENABLED');
echo __('MAINTENANCE_EMAIL_ALERTS');
echo __('MAINTENANCE_LOG_MAXAGE');
echo __('MAINTENANCE_STAT_MAXAGE');
echo __('MAINTENANCE_ALERT_TOUT');
echo __('MAINTENANCE_KEY');
echo __('MAINTENANCE_ALWAYS_ALERT');
echo __('mail_from');
echo __('mail_to');
echo __('SHOW_DISPLAY_AS_VNCLINK');
echo __('SHOW_DISPLAY_AS_VNC_TGT');
echo __('REGION_OPTIONS_COLOURING');
echo __('LAYOUT_COPY_MEDIA_CHECKB');
echo __('LIBRARY_MEDIA_UPDATEINALL_CHECKB');
echo __('USER_PASSWORD_POLICY');
echo __('USER_PASSWORD_ERROR');
echo __('MODULE_CONFIG_LOCKED_CHECKB');
echo __('LIBRARY_SIZE_LIMIT_KB');
echo __('MONTHLY_XMDS_TRANSFER_LIMIT_KB');
echo __('DEFAULT_LANGUAGE');
echo __('TRANSITION_CONFIG_LOCKED_CHECKB');
echo __('GLOBAL_THEME_NAME');
echo __('DEFAULT_LAT');
echo __('DEFAULT_LONG');
echo __('SCHEDULE_WITH_VIEW_PERMISSION');
echo __('SETTING_IMPORT_ENABLED');
echo __('SETTING_LIBRARY_TIDY_ENABLED');
echo __('EMBEDDED_STATUS_WIDGET');
echo __('PROXY_HOST');
echo __('PROXY_PORT');
echo __('PROXY_AUTH');

// Transitions
echo __('Fade In');
echo __('Fade Out');
echo __('Fly');

// Data Sets
echo __('String');
echo __('Number');
echo __('Date');

echo __('Value');
echo __('Formula');


echo __('Media will be created with these settings. If public everyone will be able to view and use this media.');
echo __('New layouts will be created with these settings. If public everyone will be able to view and use this layout.');
echo __('Sets the default user type selected when creating a user.\r\n<br />\r\nWe recommend that this is set to "User"');
echo __('Sets whether debug information is recorded when an error occurs.\r\n<br />\r\nThis should be set to "off" to ensure smaller log sizes');
echo __('This sets which user authentication module is currently being used.');
echo __('Sets the admin message to be displayed on the client page at all times');
echo __('Set the default timezone for the application');
echo __('Errors will be mailed here');
echo __('Mail will be sent from this address');
echo __('This is the fully qualified URI of the site. e.g http://www.xibo.co.uk/');
echo __('Default length for JPG files (in seconds)');
echo __('Default length for PPT files');
echo __('Default height for PPT files');
echo __('Default length for PPT files (in seconds)');
echo __('Default length for SWF files');
echo __('Turn on the auditing information. Warning this will quickly fill up the log');
echo __('Should the server send anonymous statistics back to the Xibo project?');
echo __('Key used to distinguish each Xibo instance. This is generated randomly based on the time you first installed Xibo, and is completely untraceable.');
echo __('The URL to connect to to PHONE_HOME (if enabled)');
echo __('The last time we PHONED_HOME in seconds since the epoch');
echo __('This should only be set if you want to display the maximum allowed error messaging through the user interface. <br /> Useful for capturing critical php errors and environment issues.');
echo __('Allow the maintenance script to run if it is called?');
echo __('Global switch for email alerts to be sent');
echo __('String appended to the maintenance script to prevent malicious calls to the script.');
echo __('Maximum age for log entries. Set to 0 to keep logs indefinitely.');
echo __('Maximum age for statistics entries. Set to 0 to keep statistics indefinitely.');
echo __('How long in minutes after the last time a client connects should we send an alert? Can be overridden on a per client basis.');


echo __('January');
echo __('February');
echo __('March');
echo __('April');
echo __('May');
echo __('June');
echo __('July');
echo __('August');
echo __('September');
echo __('October');
echo __('November');
echo __('December');

?>
