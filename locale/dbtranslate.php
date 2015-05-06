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
echo __('Display Settings');
echo __('Help Links');
echo __('Audit Trail');

// Settings translations
echo __('Media Permissions');
echo __('Layout Permissions');
echo __('Default User Type');
echo __('User Module');
echo __('Timezone');
echo __('Admin email address');
echo __('Sending email address');
echo __('Default Image Duration');
echo __('Default PowerPoint Duration');
echo __('Default Flash Duration');
echo __('Log Level');
echo __('Library Location');
echo __('CMS Secret Key');
echo __('Location of the Manual');
echo __('Allow usage tracking?');
echo __('Phone home key');
echo __('Phone home URL');
echo __('Phone home time');
echo __('Server Mode');
echo __('Enable Maintenance?');
echo __('Enable Email Alerts?');
echo __('Maintenance Key');
echo __('Max Log Age');
echo __('Max Statistics Age');
echo __('Max Display Timeout');
echo __('Display a VNC Link?');
echo __('Open VNC Link in new window?');
echo __('Send repeat Display Timeouts');
echo __('Send Schedule in advance?');
echo __('Send files in advance?');
echo __('How to colour Media on the Region Timeline');
echo __('Default copy media when copying a layout?');
echo __('Number of display slots');
echo __('Default update media in all layouts');
echo __('Password Policy Regular Expression');
echo __('Description of Password Policy');
echo __('Lock Module Config');
echo __('Library Size Limit');
echo __('Monthly bandwidth Limit');
echo __('Default Language');
echo __('Allow modifications to the transition configuration?');
echo __('CMS Theme');
echo __('Default Latitude');
echo __('Default Longitude');
echo __('Schedule with view permissions?');
echo __('Allow Import?');
echo __('Enable Library Tidy?');
echo __('File download mode');
echo __('Status Dashboard Widget');
echo __('Proxy URL');
echo __('Proxy Port');
echo __('Proxy Credentials');
echo __('Date Format');
echo __('The Date Format to use when displaying dates in the CMS.');
echo __('Detect language?');
echo __('Detect the browser language?');
echo __('Force HTTPS?');
echo __('Force the portal into HTTPS?');
echo __('Enable STS?');
echo __('Add STS to the response headers? Make sure you fully understand STS before turning it on as it will prevent access via HTTP after the first successful HTTPS connection.');
echo __('The Time to Live (maxage) of the STS header expressed in minutes.');
echo __('Maintenance Alerts for Users');
echo __('Email maintenance alerts for users with view permissions to effected Displays.');
echo __('Set the level of logging the CMS should record. In production systems "error" is recommended.');
echo __('Which Calendar Type should the CMS use?');
echo __('Enable Latest News?');
echo __('Default for "Delete old version of Media" checkbox. Shown when Editing Library Media.');
echo __('Should the Dashboard show latest news? The address is provided by the theme.');
echo __('Proxy Exceptions');
echo __('Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.');

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

// Module names
echo __('DataSet View');
echo __('Ticker');
echo __('Text');
echo __('Embedded');
echo __('Image');
echo __('Video');
echo __('Flash');
echo __('PowerPoint');
echo __('Web Page');
echo __('Counter');
echo __('Shell Command');
echo __('Local Video');
echo __('Generic File');

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
