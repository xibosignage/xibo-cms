INSERT INTO `version` (`app_ver`, `XmdsVersion`, `XlfVersion`, `DBVersion`) VALUES
('1.4.2', 3, 1, 52);

INSERT INTO `group` (`groupID`, `group`, `IsUserSpecific`, `IsEveryone`) VALUES
(1, 'Users', 0, 0),
(2, 'Everyone', 0, 1),
(3, 'xibo_admin', 1, 0);

INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) VALUES
(1, 'Layout', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts'),
(2, 'Content', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Media#The_Library'),
(4, 'Schedule', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Scheduling'),
(5, 'Group', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Groups'),
(6, 'Admin', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Settings'),
(7, 'Report', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Log'),
(8, 'Dashboard', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Overview#Dashboard'),
(9, 'User', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users'),
(10, 'Display', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays'),
(11, 'DisplayGroup', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Groups'),
(12, 'Layout', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Adding_Layouts'),
(13, 'Layout', 'Background', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Changing_the_Background'),
(14, 'Content', 'Assign', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Library'),
(15, 'Layout', 'RegionOptions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Assigning_Media'),
(16, 'Content', 'AddtoLibrary', 'http://wiki.xibo.org.uk/wiki/Manual:Media#Add_Media'),
(17, 'Display', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Edit'),
(18, 'Display', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Delete'),
(19, 'Displays', 'Groups', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Groups'),
(20, 'UserGroup', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Add_Group'),
(21, 'User', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Add'),
(22, 'User', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Delete'),
(23, 'Content', 'Config', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Settings'),
(24, 'LayoutMedia', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Permissions'),
(25, 'Region', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Region_Permissions'),
(26, 'Library', 'Assign', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#AssigningFromLibrary'),
(27, 'Media', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Media#Retire_Media'),
(28, 'DisplayGroup', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Add'),
(29, 'DisplayGroup', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Edit'),
(30, 'DisplayGroup', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Delete'),
(31, 'DisplayGroup', 'Members', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Members'),
(32, 'DisplayGroup', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#GroupSecurity'),
(34, 'Schedule', 'ScheduleNow', 'http://wiki.xibo.org.uk/wiki/Manual:Scheduling#Schedule_Now'),
(35, 'Layout', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Management#Deleting_or_Retiring_Layouts'),
(36, 'Layout', 'Copy', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Management#Copying_Layouts'),
(37, 'Schedule', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Scheduling#Editing_Schedules'),
(38, 'Schedule', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Scheduling#Scheduling_Layouts'),
(39, 'Layout', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Management#Layout_Permissions'),
(40, 'Display', 'MediaInventory', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Client_Media_Inventory'),
(41, 'User', 'ChangePassword', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Changing_Password'),
(42, 'Schedule', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Scheduling#Deleting_Events'),
(43, 'Layout', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Management#Editing_Layouts'),
(44, 'Media', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Media#Permissions'),
(45, 'Display', 'DefaultLayout', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Default_Layout'),
(46, 'UserGroup', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Edit_Group'),
(47, 'UserGroup', 'Members', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Group_Membership'),
(48, 'User', 'PageSecurity', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Page_Security'),
(49, 'User', 'MenuSecurity', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Menu_Security'),
(50, 'UserGroup', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Group_Delete'),
(51, 'User', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Edit'),
(52, 'User', 'Applications', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Applications'),
(53, 'User', 'SetHomepage', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Set_Homepage'),
(54, 'DataSet', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets'),
(55, 'DataSet', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Adding_DataSets'),
(56, 'DataSet', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Editing_Datasets'),
(57, 'DataSet', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Deleting_DataSets'),
(58, 'DataSet', 'AddColumn', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Adding_Columns'),
(59, 'DataSet', 'EditColumn', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Editing_Columns'),
(60, 'DataSet', 'DeleteColumn', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Deleting_Columns'),
(61, 'DataSet', 'Data', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Adding_Data'),
(62, 'DataSet', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Media:DataSets#Permissions'),
(63, 'Fault', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Debug#ReportFault'),
(64, 'Report', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Debug#ReportFault'),
(65, 'Stats', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Stats'),
(66, 'Resolution', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Resolutions'),
(67, 'Template', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Templates'),
(68, 'Services', 'Register', 'http://wiki.xibo.org.uk/wiki/Manual:Applications#Adding_Applications'),
(69, 'OAuth', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Applications'),
(70, 'Services', 'Log', 'http://wiki.xibo.org.uk/wiki/Manual:Applications#View_Log'),
(71, 'Module', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Media#Module_Config'),
(72, 'Module', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Media#Editing_Module_Config');

INSERT INTO `menu` (`MenuID`, `Menu`) VALUES
(8, 'Administration Menu'),
(9, 'Advanced Menu'),
(2, 'Dashboard'),
(6, 'Design Menu'),
(7, 'Display Menu'),
(5, 'Library Menu'),
(1, 'Top Nav');

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`) VALUES
(1, 'Image', 'Image', 1, 0, 'Images. PNG, JPG, BMP, GIF', 'img/forms/image.gif', 1, 'jpg,jpeg,png,bmp,gif', 1),
(2, 'Video', 'Video', 1, 0, 'Videos. WMV.', 'img/forms/video.gif', 1, 'wmv,avi,mpg,mpeg', 1),
(3, 'Flash', 'Flash', 1, 0, 'Flash', 'img/forms/flash.gif', 1, 'swf', 1),
(4, 'PowerPoint', 'PowerPoint', 1, 0, 'Powerpoint. PPT, PPS', 'img/forms/powerpoint.gif', 1, 'ppt,pps,pptx', 1),
(5, 'Webpage', 'Webpage', 1, 1, 'Webpages.', 'img/forms/webpage.gif', 1, NULL, 1),
(6, 'Ticker', 'Ticker', 1, 1, 'RSS Ticker.', 'img/forms/ticker.gif', 1, NULL, 1),
(7, 'Text', 'Text', 1, 1, 'Text. With Directional Controls.', 'img/forms/text.gif', 1, NULL, 1),
(8, 'Embedded', 'Embedded', 1, 1, 'Embedded HTML', 'img/forms/webpage.gif', 1, NULL, 1),
(9, 'MicroBlog', 'MicroBlog', 1, 1, NULL, 'img/forms/microblog.gif', 1, NULL, 1),
(10, 'Counter', 'Counter', 1, 1, 'Customer Counter connected to a Remote Control', 'img/forms/counter.gif', 1, NULL, 1),
(11, 'datasetview', 'Data Set', 1, 1, 'A view on a DataSet', 'img/forms/datasetview.gif', 1, NULL, 1),
(12, 'shellcommand', 'Shell Command', 1, 1, 'Execute a shell command on the client', 'img/forms/shellcommand.gif', 1, NULL, 1),
(13, 'localvideo', 'Local Video', 0, 1, 'Play a video locally stored on the client', 'img/forms/video.gif', 1, NULL, 1);

INSERT INTO `pagegroup` (`pagegroupID`, `pagegroup`) VALUES
(1, 'Schedule'),
(2, 'Homepage and Login'),
(3, 'Layouts'),
(4, 'Content'),
(7, 'Displays'),
(8, 'Users and Groups'),
(9, 'Reports'),
(10, 'License and Settings'),
(11, 'Updates'),
(12, 'Templates'),
(13, 'Web Services'),
(14, 'DataSets');

INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES
(1, 'dashboard', 2),
(2, 'schedule', 1),
(3, 'mediamanager', 2),
(5, 'layout', 3),
(7, 'content', 4),
(11, 'display', 7),
(12, 'update', 11),
(14, 'admin', 10),
(15, 'group', 8),
(16, 'report', 9),
(17, 'user', 8),
(18, 'license', 10),
(19, 'index', 2),
(24, 'module', 4),
(25, 'template', 3),
(26, 'fault', 10),
(27, 'stats', 9),
(28, 'manual', 2),
(29, 'resolution', 12),
(30, 'help', 2),
(31, 'clock', 2),
(32, 'displaygroup', 7),
(33, 'oauth', 13),
(34, 'help', 2),
(35, 'clock', 2),
(36, 'dataset', 14),
(37, 'campaign', 3);

INSERT INTO `menuitem` (`MenuItemID`, `MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`) VALUES
(1, 1, 2, NULL, 'Schedule', NULL, NULL, 1),
(2, 1, 5, NULL, 'Design', NULL, NULL, 2),
(3, 1, 7, NULL, 'Library', NULL, NULL, 3),
(4, 1, 17, NULL, 'Administration', NULL, NULL, 5),
(7, 7, 11, NULL, 'Displays', NULL, NULL, 1),
(8, 8, 15, NULL, 'User Groups', NULL, NULL, 2),
(9, 8, 17, NULL, 'Users', NULL, NULL, 1),
(10, 9, 16, 'sp=log', 'Log', NULL, NULL, 1),
(11, 9, 18, NULL, 'License', NULL, NULL, 4),
(12, 9, 16, 'sp=sessions', 'Sessions', NULL, NULL, 2),
(13, 8, 14, NULL, 'Settings', NULL, NULL, 3),
(14, 2, 2, 'sp=month', 'Schedule', 'schedule_button', 'img/dashboard/scheduleview.png', 1),
(15, 2, 5, NULL, 'Layouts', 'playlist_button', 'img/dashboard/presentations.png', 2),
(16, 2, 7, NULL, 'Library', 'content_button', 'img/dashboard/content.png', 3),
(17, 2, 25, NULL, 'Templates', 'layout_button', 'img/dashboard/layouts.png', 4),
(18, 2, 17, NULL, 'Users', 'user_button', 'img/dashboard/users.png', 5),
(19, 2, 14, NULL, 'Settings', 'settings_button', 'img/dashboard/settings.png', 6),
(20, 2, 18, NULL, 'License', 'license_button', 'img/dashboard/license.png', 7),
(22, 9, 26, NULL, 'Report Fault', NULL, NULL, 3),
(23, 7, 27, NULL, 'Statistics', NULL, NULL, 3),
(24, 2, 28, 'http://wiki.xibo.org.uk/wiki/Manual:TOC', 'Manual', 'help_button', 'img/dashboard/help.png', 10),
(25, 6, 29, NULL, 'Resolutions', NULL, NULL, 4),
(26, 6, 25, NULL, 'Templates', NULL, NULL, 3),
(27, 7, 32, NULL, 'Display Groups', NULL, NULL, 2),
(28, 8, 33, NULL, 'Applications', NULL, NULL, 4),
(29, 5, 36, NULL, 'DataSets', NULL, NULL, 2),
(30, 5, 7, NULL, 'Media', NULL, NULL, 1),
(33, 6, 5, NULL, 'Layouts', NULL, NULL, 2),
(34, 1, 11, NULL, 'Displays', NULL, NULL, 4),
(35, 1, 16, 'sp=log', 'Advanced', NULL, NULL, 6),
(36, 8, 24, NULL, 'Modules', NULL, NULL, 5),
(37, 6, 37, NULL, 'Campaigns', NULL, NULL, 1);

INSERT INTO `resolution` (`resolutionID`, `resolution`, `width`, `height`) VALUES
(1, '4:3 Monitor', 800, 600),
(2, '3:2 Tv', 720, 480),
(3, '16:10 Widescreen Mon', 800, 500),
(4, '16:9 HD Widescreen', 800, 450),
(5, '3:4 Monitor', 600, 800),
(6, '2:3 Tv', 480, 720),
(7, '10:16 Widescreen', 500, 800),
(8, '9:16 HD Widescreen', 450, 800);

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES
(1, 'MEDIA_DEFAULT', 'private', 'dropdown', 'Media will be created with these settings. If public everyone will be able to view and use this media.', 'private|public', 'default', 1),
(2, 'LAYOUT_DEFAULT', 'private', 'dropdown', 'New layouts will be created with these settings. If public everyone will be able to view and use this layout.', 'private|public', 'default', 1),
(3, 'defaultUsertype', 'user', 'dropdown', 'Sets the default user type selected when creating a user.\r\n<br />\r\nWe recommend that this is set to "User"', 'User|Group Admin|Super Admin', 'default', 1),
(5, 'debug', 'Off', 'dropdown', 'Sets whether debug information is recorded when an error occurs.\r\n<br />\r\nThis should be set to "off" to ensure smaller log sizes', 'On|Off', 'error', 1),
(7, 'userModule', 'module_user_general.php', 'dirselect', 'This sets which user authentication module is currently being used.', NULL, 'user', 0),
(10, 'adminMessage', '', 'text', 'Sets the admin message to be displayed on the client page at all times', NULL, 'general', 0),
(11, 'defaultTimezone', 'UTC', 'timezone', 'Set the default timezone for the application', 'Europe/London', 'default', 1),
(18, 'mail_to', 'admin@yoursite.com', 'text', 'Errors will be mailed here', NULL, 'maintenance', 1),
(19, 'mail_from', 'mail@yoursite.com', 'text', 'Mail will be sent from this address', NULL, 'maintenance', 1),
(20, 'BASE_URL', 'http://localhost/xibo/', 'text', 'This is the fully qualified URI of the site. e.g http://www.xibo.co.uk/', NULL, 'general', 0),
(23, 'jpg_length', '10', 'text', 'Default length for JPG files (in seconds)', NULL, 'content', 1),
(24, 'ppt_width', '1024', 'text', 'Default length for PPT files', NULL, 'content', 0),
(25, 'ppt_height', '768', 'text', 'Default height for PPT files', NULL, 'content', 0),
(26, 'ppt_length', '120', 'text', 'Default length for PPT files (in seconds)', NULL, 'content', 1),
(29, 'swf_length', '60', 'text', 'Default length for SWF files', NULL, 'content', 1),
(30, 'audit', 'Off', 'dropdown', 'Turn on the auditing information. Warning this will quickly fill up the log', 'On|Off', 'error', 1),
(33, 'LIBRARY_LOCATION', 'C:\\Users\\dan\\Documents\\Xibo\\release140/', 'text', NULL, NULL, 'path', 1),
(34, 'SERVER_KEY', 'xsm', 'text', NULL, NULL, 'general', 1),
(35, 'HELP_BASE', 'http://www.xibo.org.uk/manual/', 'text', NULL, NULL, 'path', 0),
(36, 'PHONE_HOME', 'Off', 'dropdown', 'Should the server send anonymous statistics back to the Xibo project?', 'On|Off', 'general', 1),
(37, 'PHONE_HOME_KEY', 'b904d63cc837b2af0b033bfcd781f364', 'text', 'Key used to distinguish each Xibo instance. This is generated randomly based on the time you first installed Xibo, and is completely untraceable.', NULL, 'general', 0),
(38, 'PHONE_HOME_URL', 'http://www.xibo.org.uk/stats/track.php', 'text', 'The URL to connect to to PHONE_HOME (if enabled)', NULL, 'path', 0),
(39, 'PHONE_HOME_DATE', '0', 'text', 'The last time we PHONED_HOME in seconds since the epoch', NULL, 'general', 0),
(40, 'SERVER_MODE', 'Production', 'dropdown', 'This should only be set if you want to display the maximum allowed error messaging through the user interface. <br /> Useful for capturing critical php errors and environment issues.', 'Production|Test', 'error', 1),
(41, 'MAINTENANCE_ENABLED', 'Off', 'dropdown', 'Allow the maintenance script to run if it is called?', 'Protected|On|Off', 'maintenance', 1),
(42, 'MAINTENANCE_EMAIL_ALERTS', 'On', 'dropdown', 'Global switch for email alerts to be sent', 'On|Off', 'maintenance', 1),
(43, 'MAINTENANCE_KEY', 'changeme', 'text', 'String appended to the maintenance script to prevent malicious calls to the script.', NULL, 'maintenance', 1),
(44, 'MAINTENANCE_LOG_MAXAGE', '30', 'text', 'Maximum age for log entries. Set to 0 to keep logs indefinitely.', NULL, 'maintenance', 1),
(45, 'MAINTENANCE_STAT_MAXAGE', '30', 'text', 'Maximum age for statistics entries. Set to 0 to keep statistics indefinitely.', NULL, 'maintenance', 1),
(46, 'MAINTENANCE_ALERT_TOUT', '12', 'text', 'How long in minutes after the last time a client connects should we send an alert? Can be overridden on a per client basis.', NULL, 'maintenance', 1),
(47, 'SHOW_DISPLAY_AS_VNCLINK', '', 'text', 'Turn the display name in display management into a VNC link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.', NULL, 'general', 1),
(48, 'SHOW_DISPLAY_AS_VNC_TGT', '_top', 'text', 'If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.', NULL, 'general', 1),
(49, 'MAINTENANCE_ALWAYS_ALERT', 'Off', 'dropdown', 'Should Xibo send an email if a display is in an error state every time the maintenance script runs?', 'On|Off', 'maintenance', 1),
(50, 'SCHEDULE_LOOKAHEAD', 'On', 'dropdown', 'Should Xibo send future schedule information to clients?', 'On|Off', 'general', 0),
(51, 'REQUIRED_FILES_LOOKAHEAD', '172800', 'text', 'How many seconds in to the future should the calls to RequiredFiles look?', NULL, 'general', 1),
(52, 'REGION_OPTIONS_COLOURING', 'media', 'dropdown', NULL, 'Media Colouring|Permissions Colouring', 'permissions', 1),
(53, 'LAYOUT_COPY_MEDIA_CHECKB', 'Unchecked', 'dropdown', 'Default the checkbox for making duplicates of media when copying layouts', 'Checked|Unchecked', 'default', 1),
(54, 'MAX_LICENSED_DISPLAYS', '0', 'text', 'The maximum number of licensed clients for this server installation. 0 = unlimited', NULL, 'general', 0),
(55, 'LIBRARY_MEDIA_UPDATEINALL_CHECKB', 'Unchecked', 'dropdown', 'Default the checkbox for updating media on all layouts when editing in the library', 'Checked|Unchecked', 'default', 1),
(56, 'USER_PASSWORD_POLICY', '', 'text', 'Regular Expression for password complexity, leave blank for no policy.', '', 'permissions', 1),
(57, 'USER_PASSWORD_ERROR', '', 'text', 'A text description of this password policy. Will be show to users when their password does not meet the required policy', '', 'permissions', 1),
(58, 'MODULE_CONFIG_LOCKED_CHECKB', 'Unchecked', 'dropdown', 'Is the module config locked? Useful for Service providers.', 'Checked|Unchecked', 'general', 0),
(59, 'LIBRARY_SIZE_LIMIT_KB', '0', 'text', 'The Limit for the Library Size in KB', NULL, 'content', 0),
(60, 'MONTHLY_XMDS_TRANSFER_LIMIT_KB', '0', 'text', 'XMDS Transfer Limit in KB/month', NULL, 'general', 0),
(61, 'DEFAULT_LANGUAGE', 'en_GB', 'text', 'The default language to use', NULL, 'general', 1);


INSERT INTO `usertype` (`usertypeid`, `usertype`) VALUES
(1, 'Super Admin'),
(2, 'Group Admin'),
(3, 'User');

INSERT INTO `user` (`UserID`, `usertypeid`, `UserName`, `UserPassword`, `loggedin`, `lastaccessed`, `email`, `homepage`, `Retired`) VALUES
(1, 1, 'xibo_admin', '21232f297a57a5a743894a0e4a801fc3', 1, '2013-02-02 15:07:29', 'info@xibo.org.uk', 'dashboard', 0);

INSERT INTO `template` (`templateID`, `template`, `xml`, `userID`, `createdDT`, `modifiedDT`, `description`, `tags`, `thumbnail`, `isSystem`, `retired`) VALUES
(1, 'Full Screen 16:9', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="450" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="450" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(2, 'Full Screen 16:10', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="500" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="500" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(3, 'Full Screen 4:3', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="600" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="600" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(4, 'Full Screen 3:2', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="720" height="480" bgcolor="#000000"><region id="47ff29524ce1b" width="720" height="480" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(5, 'Portrait - 10:16', '<?xml version="1.0"?>\n<layout width="500" height="800" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524ae1b" width="500" height="800" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0),
(6, 'Portrait - 9:16', '<?xml version="1.0"?>\n<layout width="450" height="800" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524be1b" width="450" height="800" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0),
(7, 'Portrait - 3:4', '<?xml version="1.0"?>\n<layout width="600" height="800" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524ce1b" width="600" height="800" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0),
(8, 'Portrait - 2:3', '<?xml version="1.0"?>\n<layout width="480" height="720" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524de1b" width="480" height="720" top="0" left="0"/></layout>\n', 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0);

INSERT INTO `layout` (`layoutID`, `layout`, `xml`, `userID`, `createdDT`, `modifiedDT`, `description`, `tags`, `templateID`, `retired`, `duration`, `background`) VALUES
(4, 'Default Layout', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="500" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="500" top="0" left="0"><media id="ba2b90ee2e21f9aaffbc45f253068c60" type="text" duration="20" lkid="" schemaVersion="1">\n					<options><direction>none</direction></options>\n					<raw><text><![CDATA[<h1 style="text-align: center;"><span style="font-size: 2em;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);">Welcome to </span></span></span></h1><h1 style="text-align: center;"><span style="font-size: 2em;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);">Xibo! </span></span></span></h1><h1 style="text-align: center;"><span style="font-size: 2em;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);">Open Source Digital Signage</span></span></span></h1><p style="text-align: center;"><span style="font-family: Verdana;"><span style="color: rgb(255, 255, 255);"><span style="font-size: 1.6em;">This is the default layout - please feel free to change it whenever you like!</span></span></span></p>]]></text></raw>\n				</media><media id="7695b17df85b666d420c232ee768ef68" type="ticker" duration="100" lkid="" schemaVersion="1" userId="">\n					<options><direction>up</direction><uri>http%3A%2F%2Fxibo.org.uk%2Ffeed%2F</uri><copyright/><scrollSpeed>1</scrollSpeed><updateInterval>30</updateInterval><numItems/><takeItemsFrom>start</takeItemsFrom><durationIsPerItem>0</durationIsPerItem><fitText>0</fitText></options>\n					<raw><template><![CDATA[<h2 style="text-align: center;">\n	<span style="color:#ffffff;"><span style="font-size: 48px;">[Title]</span></span></h2>\n<p>\n	<span style="color:#ffffff;"><span style="font-size: 36px;">[Description]</span></span></p>]]></template></raw>\n				</media></region></layout>\n', 1, '2013-02-02 14:30:40', '2013-02-02 14:30:40', NULL, NULL, NULL, 0, 0, NULL);

INSERT INTO `campaign` (`CampaignID`, `Campaign`, `IsLayoutSpecific`, `UserID`) VALUES
(1, 'Default Layout', 1, 1);

INSERT INTO `lkcampaignlayout` (`LkCampaignLayoutID`, `CampaignID`, `LayoutID`, `DisplayOrder`) VALUES
(1, 1, 4, 1);

INSERT INTO `lkmenuitemgroup` (`LkMenuItemGroupID`, `GroupID`, `MenuItemID`) VALUES
(1, 1, 33),
(2, 1, 14),
(3, 1, 15),
(4, 1, 16),
(5, 1, 20),
(6, 1, 24),
(7, 1, 1),
(8, 1, 2),
(9, 1, 3),
(10, 1, 29),
(11, 1, 30),
(12, 1, 26);

INSERT INTO `lkpagegroup` (`lkpagegroupID`, `pageID`, `groupID`) VALUES
(12, 2, 1),
(36, 1, 1),
(37, 3, 1),
(38, 19, 1),
(48, 5, 1),
(51, 7, 1),
(54, 24, 1);

INSERT INTO `lktemplategroup` (`LkTemplateGroupID`, `TemplateID`, `GroupID`, `View`, `Edit`, `Del`) VALUES
(1, 1, 2, 1, 0, 0),
(2, 2, 2, 1, 0, 0),
(3, 3, 2, 1, 0, 0),
(4, 4, 2, 1, 0, 0),
(5, 5, 2, 1, 0, 0),
(6, 6, 2, 1, 0, 0),
(7, 7, 2, 1, 0, 0),
(8, 8, 2, 1, 0, 0);

INSERT INTO `lkusergroup` (`LkUserGroupID`, `GroupID`, `UserID`) VALUES
(10, 3, 1);
