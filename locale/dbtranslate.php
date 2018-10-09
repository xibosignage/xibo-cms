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


/* Translations from the Database that need to be registered with Gettext */

echo __('Dashboard');
echo __('Media Dashboard');
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
echo __('Update');
echo __('Commands');
echo __('Notification Drawer');
echo __('Notifications');
echo __('Dayparting');
echo __('Tasks');

// Settings translations
echo __('Media Permissions');
echo __('Layout Permissions');
echo __('Default User Type');
echo __('User Module');
echo __('Timezone');
echo __('Admin email address');
echo __('Sending email address');
echo __('Sending email name');
echo __('Mail will be sent under this name');
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
echo __('Add a link to the Display name using this format mask?');
echo __('The target attribute for the above link');
echo __('Send repeat Display Timeouts');
echo __('Should Xibo send an email if a display is in an error state every time the maintenance script runs?');
echo __('Send Schedule in advance?');
echo __('Should Xibo send future schedule information to clients?');
echo __('Send files in advance?');
echo __('How many seconds in to the future should the calls to RequiredFiles look?');
echo __('How to colour Media on the Region Timeline');
echo __('Default copy media when copying a layout?');
echo __('Number of display slots');
echo __('The maximum number of licensed clients for this server installation. 0 = unlimited');
echo __('Default update media in all layouts');
echo __('Password Policy Regular Expression');
echo __('Regular Expression for password complexity, leave blank for no policy.');
echo __('Description of Password Policy');
echo __('A text description of this password policy. Will be show to users when their password does not meet the required policy');
echo __('Lock Module Config');
echo __('Library Size Limit');
echo __('The Limit for the Library Size in KB');
echo __('Monthly bandwidth Limit');
echo __('XMDS Transfer Limit in KB/month');
echo __('Default Language');
echo __('The default language to use');
echo __('Allow modifications to the transition configuration?');
echo __('CMS Theme');
echo __('The Theme to apply to all pages by default');
echo __('Default Latitude');
echo __('The Latitude to apply for any Geo aware Previews');
echo __('Default Longitude');
echo __('The longitude to apply for any Geo aware Previews');
echo __('Schedule with view permissions?');
echo __('Should users with View permissions on displays be allowed to schedule to them?');
echo __('Allow Import?');
echo __('Enable Library Tidy?');
echo __('File download mode');
echo __('Status Dashboard Widget');
echo __('HTML to embed in an iframe on the Status Dashboard');
echo __('Proxy URL');
echo __('The Proxy URL');
echo __('Proxy Port');
echo __('The Proxy Port');
echo __('Proxy Credentials');
echo __('The Authentication information for this proxy. username:password');
echo __('Date Format');
echo __('The Date Format to use when displaying dates in the CMS.');
echo __('Detect language?');
echo __('Detect the browser language?');
echo __('Force HTTPS?');
echo __('Force the portal into HTTPS?');
echo __('Enable STS?');
echo __('Add STS to the response headers? Make sure you fully understand STS before turning it on as it will prevent access via HTTP after the first successful HTTPS connection.');
echo __('STS Time out');
echo __('The Time to Live (maxage) of the STS header expressed in seconds.');
echo __('Maintenance Alerts for Users');
echo __('Email maintenance alerts for users with view permissions to effected Displays.');
echo __('Set the level of logging the CMS should record. In production systems "error" is recommended.');
echo __('Calendar Type');
echo __('Which Calendar Type should the CMS use?');
echo __('Enable Latest News?');
echo __('Default for "Delete old version of Media" checkbox. Shown when Editing Library Media.');
echo __('Should the Dashboard show latest news? The address is provided by the theme.');
echo __('Proxy Exceptions');
echo __('Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.');
echo __('Instance Suspended');
echo __('Is this instance suspended?');
echo __('Inherit permissions');
echo __('Inherit permissions from Parent when adding a new item?');
echo __('XMR Private Address');
echo __('XMR Public Address');
echo __('Please enter the private address for XMR.');
echo __('Please enter the public address for XMR.');
echo __('CDN Address');
echo __('Content Delivery Network Address for serving file requests to Players');
echo __('Elevate Log Until');
echo __('Elevate the log level until this date.');
echo __('Resting Log Level');
echo __('Set the level of the resting log level. The CMS will revert to this log level after an elevated period ends. In production systems "error" is recommended.');
echo __('Lock Task Config');
echo __('Is the task config locked? Useful for Service providers.');
echo __('Whitelist Load Balancers');
echo __('If the CMS is behind a load balancer, what are the load balancer IP addresses, comma delimited.');
echo __('Default Layout');
echo __('The default layout to assign for new displays and displays which have their current default deleted.');
echo __('Default setting for Statistics Enabled?');
echo __('Enable the option to report the current layout status?');
echo __('Enable the option to set the screenshot interval?');
echo __('The default size in pixels for the Display Screenshots');
echo __('Display Screenshot Default Size');
echo __('Latest News URL');
echo __('RSS/Atom Feed to be displayed on the Status Dashboard');
echo __('Lock the Display Name to the device name provided by the Player?');
echo __('Sending email name');
echo __('Mail will be sent under this name');
echo __('Turn the display name in display management into a link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.');
echo __('If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.');
echo __('Configuration');
echo __('Defaults');
echo __('Default the checkbox for updating media on all layouts when editing in the library');
echo __('Default the checkbox for making duplicates of media when copying layouts');
echo __('Is the Transition config locked?');
echo __('Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.');
echo __('Network');
echo __('Regional');
echo __('Show event Layout regardless of User permission?');
echo __('If checked then the Schedule will show the Layout for existing events even if the logged in User does not have permission to see that Layout.');
echo __('Default User Group');
echo __('The default User Group for new Users');
echo __('Password Reminder');
echo __('Is password reminder enabled?');
echo __('On except Admin');


// Transitions
echo __('Fade In');
echo __('Fade Out');
echo __('Fly');

// Data Sets
echo __('String');
echo __('Number');
echo __('Date');
echo __('External Image');
echo __('Library Image');

echo __('Value');
echo __('Formula');

// Module names
echo __('Data Set');
echo __('DataSet View');
echo __('A view on a DataSet');
echo __('Ticker');
echo __('RSS Ticker.');
echo __('Text');
echo __('Text. With Directional Controls.');
echo __('Embedded');
echo __('Embedded HTML');
echo __('Image');
echo __('Images. PNG, JPG, BMP, GIF');
echo __('Video');
echo __('Videos - support varies depending on the client hardware you are using.');
echo __('Video In');
echo __('A module for displaying Video and Audio from an external source');
echo __('Flash');
echo __('PowerPoint');
echo __('Powerpoint. PPT, PPS');
echo __('Webpage');
echo __('Webpages.');
echo __('Counter');
echo __('Shell Command');
echo __('Execute a shell command on the client');
echo __('Local Video');
echo __('Play a video locally stored on the client');
echo __('Clock');
echo __('Font');
echo __('A font to use in other Modules');
echo __('Generic File');
echo __('A generic file to be stored in the library');
echo __('Audio');
echo __('Audio - support varies depending on the client hardware');
echo __('PDF');
echo __('PDF document viewer');
echo __('Notification');
echo __('Display Notifications from the Notification Centre');

echo __('Stocks Module');
echo __('A module for showing Stock quotes');
echo __('Currencies Module');
echo __('A module for showing Currency pairs and exchange rates');

echo __('Stocks');
echo __('Yahoo Stocks');
echo __('Currencies');
echo __('Yahoo Currencies');
echo __('Finance');
echo __('Yahoo Finance');
echo __('Google Traffic');
echo __('Google Traffic Map');
echo __('HLS');
echo __('HLS Video Stream');
echo __('Twitter');
echo __('Twitter Search Module');
echo __('Twitter Metro');
echo __('Twitter Metro Search Module');
echo __('Weather');
echo __('Weather Powered by DarkSky');

echo __('Sub-Playlist');
echo __('Embed a Sub-Playlist');


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
echo __('Maximum age for log entries in days. Set to 0 to keep logs indefinitely.');
echo __('Maximum age for statistics entries in days. Set to 0 to keep statistics indefinitely.');
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
