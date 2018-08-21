INSERT INTO `version` (`app_ver`, `XmdsVersion`, `XlfVersion`, `DBVersion`) VALUES
('1.8.11', 5, 2, 142);

INSERT INTO `group` (`groupID`, `group`, `IsUserSpecific`, `IsEveryone`, `isSystemNotification`) VALUES
(1, 'Users', 0, 0, 0),
(2, 'Everyone', 0, 1, 0),
(3, 'xibo_admin', 1, 0, 1),
(4, 'System Notifications', 0, 0, 1);

INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) VALUES
(1, 'Layout', 'General', 'layouts.html'),
(2, 'Content', 'General', 'media.html'),
(4, 'Schedule', 'General', 'scheduling.html'),
(5, 'Group', 'General', 'users_groups.html'),
(6, 'Admin', 'General', 'cms_settings.html'),
(7, 'Report', 'General', 'troubleshooting.html'),
(8, 'Dashboard', 'General', 'tour.html'),
(9, 'User', 'General', 'users.html'),
(10, 'Display', 'General', 'displays.html'),
(11, 'DisplayGroup', 'General', 'displays_groups.html'),
(12, 'Layout', 'Add', 'layouts.html#Add_Layout'),
(13, 'Layout', 'Background', 'layouts_designer.html#Background'),
(14, 'Content', 'Assign', 'layouts_playlists.html#Assigning_Content'),
(15, 'Layout', 'RegionOptions', 'layouts_regions.html'),
(16, 'Content', 'AddtoLibrary', 'media_library.html'),
(17, 'Display', 'Edit', 'displays.html#Display_Edit'),
(18, 'Display', 'Delete', 'displays.html#Display_Delete'),
(19, 'Displays', 'Groups', 'displays_groups.html#Group_Members'),
(20, 'UserGroup', 'Add', 'users_groups.html#Adding_Group'),
(21, 'User', 'Add', 'users_administration.html#Add_User'),
(22, 'User', 'Delete', 'users_administration.html#Delete_User'),
(23, 'Content', 'Config', 'cms_settings.html#Content'),
(24, 'LayoutMedia', 'Permissions', 'users_permissions.html'),
(25, 'Region', 'Permissions', 'users_permissions.html'),
(26, 'Library', 'Assign', 'layouts_playlists.html#Add_From_Library'),
(27, 'Media', 'Delete', 'media_library.html#Delete'),
(28, 'DisplayGroup', 'Add', 'displays_groups.html#Add_Group'),
(29, 'DisplayGroup', 'Edit', 'displays_groups.html#Edit_Group'),
(30, 'DisplayGroup', 'Delete', 'displays_groups.html#Delete_Group'),
(31, 'DisplayGroup', 'Members', 'displays_groups.html#Group_Members'),
(32, 'DisplayGroup', 'Permissions', 'users_permissions.html'),
(34, 'Schedule', 'ScheduleNow', 'scheduling_now.html'),
(35, 'Layout', 'Delete', 'layouts.html#Delete_Layout'),
(36, 'Layout', 'Copy', 'layouts.html#Copy_Layout'),
(37, 'Schedule', 'Edit', 'scheduling_events.html#Edit'),
(38, 'Schedule', 'Add', 'scheduling_events.html#Add'),
(39, 'Layout', 'Permissions', 'users_permissions.html'),
(40, 'Display', 'MediaInventory', 'displays.html#Media_Inventory'),
(41, 'User', 'ChangePassword', 'users.html#Change_Password'),
(42, 'Schedule', 'Delete', 'scheduling_events.html'),
(43, 'Layout', 'Edit', 'layouts_designer.html#Edit_Layout'),
(44, 'Media', 'Permissions', 'users_permissions.html'),
(45, 'Display', 'DefaultLayout', 'displays.html#DefaultLayout'),
(46, 'UserGroup', 'Edit', 'users_groups.html#Edit_Group'),
(47, 'UserGroup', 'Members', 'users_groups.html#Group_Member'),
(48, 'User', 'PageSecurity', 'users_permissions.html#Page_Security'),
(49, 'User', 'MenuSecurity', 'users_permissions.html#Menu_Security'),
(50, 'UserGroup', 'Delete', 'users_groups.html#Delete_Group'),
(51, 'User', 'Edit', 'users_administration.html#Edit_User'),
(52, 'User', 'Applications', 'users_administration.html#Users_MyApplications'),
(53, 'User', 'SetHomepage', 'users_administration.html#Media_Dashboard'),
(54, 'DataSet', 'General', 'media_datasets.html'),
(55, 'DataSet', 'Add', 'media_datasets.html#Create_Dataset'),
(56, 'DataSet', 'Edit', 'media_datasets.html#Edit_Dataset'),
(57, 'DataSet', 'Delete', 'media_datasets.html#Delete_Dataset'),
(58, 'DataSet', 'AddColumn', 'media_datasets.html#Dataset_Column'),
(59, 'DataSet', 'EditColumn', 'media_datasets.html#Dataset_Column'),
(60, 'DataSet', 'DeleteColumn', 'media_datasets.html#Dataset_Column'),
(61, 'DataSet', 'Data', 'media_datasets.html#Dataset_Row'),
(62, 'DataSet', 'Permissions', 'users_permissions.html'),
(63, 'Fault', 'General', 'troubleshooting.html#Report_Fault'),
(65, 'Stats', 'General', 'displays_metrics.html'),
(66, 'Resolution', 'General', 'layouts_resolutions.html'),
(67, 'Template', 'General', 'layouts_templates.html'),
(68, 'Services', 'Register', '#Registered_Applications'),
(69, 'OAuth', 'General', 'api_oauth.html'),
(70, 'Services', 'Log', 'api_oauth.html#oAuthLog'),
(71, 'Module', 'Edit', 'media_modules.html'),
(72, 'Module', 'General', 'media_modules.html'),
(73, 'Campaign', 'General', 'layouts_campaigns.html'),
(74, 'License', 'General', 'licence_information.html'),
(75, 'DataSet', 'ViewColumns', 'media_datasets.html#Dataset_Column'),
(76, 'Campaign', 'Permissions', 'users_permissions.html'),
(77, 'Transition', 'Edit', 'layouts_transitions.html'),
(78, 'User', 'SetPassword', 'users_administration.html#Set_Password'),
(79, 'DataSet', 'ImportCSV', 'media_datasets.htmlmedia_datasets.html#Import_CSV'),
(80, 'DisplayGroup', 'FileAssociations', 'displays_fileassociations.html'),
(81, 'Statusdashboard', 'General', 'tour_status_dashboard.html'),
(82, 'Displayprofile', 'General', 'displays_settings.html'),
(83, 'DisplayProfile', 'Edit', 'displays_settings.html#edit'),
(84, 'DisplayProfile', 'Delete', 'displays_settings.html#delete');

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`, `class`, `defaultDuration`) VALUES
  (1, 'Image', 'Image', 1, 0, 'Images. PNG, JPG, BMP, GIF', 'forms/image.gif', 1, 'jpg,jpeg,png,bmp,gif', 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\Image', 10),
  (2, 'Video', 'Video', 1, 0, 'Videos - support varies depending on the client hardware you are using.', 'forms/video.gif', 1, 'wmv,avi,mpg,mpeg,webm,mp4', 0, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\Video', 0),
  (3, 'Flash', 'Flash', 1, 0, 'Flash', 'forms/flash.gif', 1, 'swf', 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\Flash', 10),
  (4, 'PowerPoint', 'PowerPoint', 1, 0, 'Powerpoint. PPT, PPS', 'forms/powerpoint.gif', 1, 'ppt,pps,pptx', 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\PowerPoint', 10),
  (5, 'Webpage', 'Webpage', 1, 1, 'Webpages.', 'forms/webpage.gif', 1, NULL, 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\WebPage', 60),
  (6, 'Ticker', 'Ticker', 1, 1, 'RSS Ticker.', 'forms/ticker.gif', 1, NULL, 1, 1, NULL, '[]', '../modules', 'Xibo\\Widget\\Ticker', 5),
  (7, 'Text', 'Text', 1, 1, 'Text. With Directional Controls.', 'forms/text.gif', 1, NULL, 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\Text', 5),
  (8, 'Embedded', 'Embedded', 1, 1, 'Embedded HTML', 'forms/webpage.gif', 1, NULL, 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\Embedded', 60),
  (11, 'datasetview', 'Data Set', 1, 1, 'A view on a DataSet', 'forms/datasetview.gif', 1, NULL, 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\DataSetView', 60),
  (12, 'shellcommand', 'Shell Command', 1, 1, 'Execute a shell command on the client', 'forms/shellcommand.gif', 1, NULL, 1, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\ShellCommand', 3),
  (13, 'localvideo', 'Local Video', 1, 1, 'Play a video locally stored on the client', 'forms/video.gif', 1, NULL, 0, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\LocalVideo', 60),
  (14, 'genericfile', 'Generic File', 1, 0, 'A generic file to be stored in the library', 'forms/library.gif', 1, 'apk,ipk,js,html,htm', 0, 0, NULL, NULL, '../modules', 'Xibo\\Widget\\GenericFile', 10),
  (15, 'clock', 'Clock', 1, 1, '', 'forms/library.gif', 1, NULL, 1, 1, 'html', '[]', '../modules', 'Xibo\\Widget\\Clock', 5),
  (16, 'font', 'Font', 1, 0, 'A font to use in other Modules', 'forms/library.gif', 1, 'ttf,otf,eot,svg,woff', 0, 0, NULL, NULL, '../modules', 'Xibo\\Widget\\Font', 10),
  (17, 'audio', 'Audio', 1, 0, 'Audio - support varies depending on the client hardware', 'forms/video.gif', 1, 'mp3,wav', 0, 1, NULL, NULL, '../modules', 'Xibo\\Widget\\Audio', 0),
  (18, 'pdf', 'PDF', 1, 0, 'PDF document viewer', 'forms/pdf.gif', 1, 'pdf', 1, 1, 'html', null, '../modules', 'Xibo\\Widget\\Pdf', 60),
  (19, 'notificationview', 'Notification', 1, 1, 'Display Notifications from the Notification Centre', 'forms/library.gif', 1, null, 1, 1, 'html', null, '../modules', 'Xibo\\Widget\\NotificationView', 10);

INSERT INTO `pages` (`pageID`, `name`, `title`, `asHome`) VALUES
  (1, 'dashboard', 'Dashboard', 1),
  (2, 'schedule', 'Schedule', 1),
  (3, 'mediamanager', 'Media Dashboard', 1),
  (4, 'layout', 'Layout', 1),
  (5, 'library', 'Library', 1),
  (6, 'display', 'Displays', 1),
  (7, 'update', 'Update', 0),
  (8, 'admin', 'Administration', 0),
  (9, 'group', 'User Groups', 1),
  (10, 'log', 'Log', 1),
  (11, 'user', 'Users', 1),
  (12, 'license', 'Licence', 1),
  (13, 'index', 'Home', 0),
  (14, 'module', 'Modules', 1),
  (15, 'template', 'Templates', 1),
  (16, 'fault', 'Report Fault', 1),
  (17, 'stats', 'Statistics', 1),
  (18, 'manual', 'Manual', 0),
  (19, 'resolution', 'Resolutions', 1),
  (20, 'help', 'Help Links', 1),
  (21, 'clock', 'Clock', 0),
  (22, 'displaygroup', 'Display Groups', 1),
  (23, 'application', 'Applications', 1),
  (24, 'dataset', 'DataSets', 1),
  (25, 'campaign', 'Campaigns', 1),
  (26, 'transition', 'Transitions', 1),
  (27, 'sessions', 'Sessions', 1),
  (28, 'preview', 'Preview', 0),
  (29, 'statusdashboard', 'Status Dashboard', 1),
  (30, 'displayprofile', 'Display Profiles', 1),
  (31, 'audit', 'Audit Trail', 0),
  (32, 'region', 'Regions', 0),
  (33, 'playlist', 'Playlist', 0),
  (34, 'maintenance', 'Maintenance', 0),
  (35, 'command', 'Commands', 1),
  (36, 'notification', 'Notifications', 0),
  (37, 'drawer', 'Notification Drawer', 0),
  (38, 'daypart', 'Dayparting', 0),
  (39, 'task', 'Tasks', 1);


INSERT INTO `resolution` (`resolutionID`, `resolution`, `width`, `height`, `intended_width`, `intended_height`, `version`, `enabled`, `userId`) VALUES
(9, '1080p HD Landscape', 800, 450, 1920, 1080, 2, 1, 0),
(10, '720p HD Landscape', 800, 450, 1280, 720, 2, 1, 0),
(11, '1080p HD Portrait', 450, 800, 1080, 1920, 2, 1, 0),
(12, '720p HD Portrait', 450, 800, 720, 1280, 2, 1, 0),
(13, '4k cinema', 800, 450, 4096, 2304, 2, 1, 0),
(14, 'Common PC Monitor 4:3', 800, 600, 1024, 768, 2, 1, 0),
(15, '4k UHD Landscape', 450, 800, 3840, 2160, 2, 1, 0),
(16, '4k UHD Portrait', 800, 450, 2160, 3840, 2, 1, 0);

INSERT INTO `setting` (`settingid`, `setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES
(1, 'MEDIA_DEFAULT', 'private', 'dropdown', 'Media will be created with these settings. If public everyone will be able to view and use this media.', 'private|group|group write|public|public write', 'permissions', 1, 'Media Permissions', '', 20, 'private', 1, 'word'),
(2, 'LAYOUT_DEFAULT', 'private', 'dropdown', 'New layouts will be created with these settings. If public everyone will be able to view and use this layout.', 'private|group|group write|public|public write', 'permissions', 1, 'Layout Permissions', '', 10, 'private', 1, 'word'),
(3, 'defaultUsertype', 'User', 'dropdown', 'Sets the default user type selected when creating a user.\r\n<br />\r\nWe recommend that this is set to "User"', 'User|Group Admin|Super Admin', 'users', 1, 'Default User Type', '', 10, 'User', 1, 'string'),
(7, 'userModule', 'module_user_general.php', 'dirselect', 'This sets which user authentication module is currently being used.', NULL, 'users', 0, 'User Module', '', 0, 'module_user_general.php', 0, 'string'),
(11, 'defaultTimezone', 'Europe/London', 'timezone', 'Set the default timezone for the application', 'Europe/London', 'regional', 1, 'Timezone', '', 20, 'Europe/London', 1, 'string'),
(18, 'mail_to', 'mail@yoursite.com', 'email', 'Errors will be mailed here', NULL, 'maintenance', 1, 'Admin email address', '', 30, 'mail@yoursite.com', 1, 'string'),
(19, 'mail_from', 'mail@yoursite.com', 'email', 'Mail will be sent from this address', NULL, 'maintenance', 1, 'Sending email address', '', 40, 'mail@yoursite.com', 1, 'string'),
(30, 'audit', 'Error', 'dropdown', 'Set the level of logging the CMS should record. In production systems "error" is recommended.', 'Emergency|Alert|Critical|Error|Warning|Notice|Info|Debug', 'troubleshooting', 1, 'Log Level', '', 20, 'error', 1, 'word'),
(33, 'LIBRARY_LOCATION', '', 'text', 'The fully qualified path to the CMS library location.', NULL, 'configuration', 1, 'Library Location', 'required', 10, '', 1, 'string'),
(34, 'SERVER_KEY', '', 'text', NULL, NULL, 'configuration', 1, 'CMS Secret Key', 'required', 20, '', 1, 'string'),
(35, 'HELP_BASE', 'http://www.xibo.org.uk/manual/en/', 'text', NULL, NULL, 'general', 1, 'Location of the Manual', 'required', 10, 'http://www.xibo.org.uk/manual/', 1, 'string'),
(36, 'PHONE_HOME', 'On', 'dropdown', 'Should the server send anonymous statistics back to the Xibo project?', 'On|Off', 'general', 1, 'Allow usage tracking?', '', 10, 'On', 1, 'word'),
(37, 'PHONE_HOME_KEY', '', 'text', 'Key used to distinguish each Xibo instance. This is generated randomly based on the time you first installed Xibo, and is completely untraceable.', NULL, 'general', 0, 'Phone home key', '', 20, '', 0, 'string'),
(38, 'PHONE_HOME_URL', 'http://www.xibo.org.uk/stats/track.php', 'text', 'The URL to connect to to PHONE_HOME (if enabled)', NULL, 'network', 0, 'Phone home URL', '', 60, 'http://www.xibo.org.uk/stats/track.php', 0, 'string'),
(39, 'PHONE_HOME_DATE', '0', 'text', 'The last time we PHONED_HOME in seconds since the epoch', NULL, 'general', 0, 'Phone home time', '', 30, '0', 0, 'int'),
(40, 'SERVER_MODE', 'Production', 'dropdown', 'This should only be set if you want to display the maximum allowed error messaging through the user interface. <br /> Useful for capturing critical php errors and environment issues.', 'Production|Test', 'troubleshooting', 1, 'Server Mode', '', 30, 'Production', 1, 'word'),
(41, 'MAINTENANCE_ENABLED', 'Off', 'dropdown', 'Allow the maintenance script to run if it is called?', 'Protected|On|Off', 'maintenance', 1, 'Enable Maintenance?', '', 10, 'Off', 1, 'word'),
(42, 'MAINTENANCE_EMAIL_ALERTS', 'On', 'dropdown', 'Global switch for email alerts to be sent', 'On|Off', 'maintenance', 1, 'Enable Email Alerts?', '', 20, 'On', 1, 'word'),
(43, 'MAINTENANCE_KEY', 'changeme', 'text', 'String appended to the maintenance script to prevent malicious calls to the script.', NULL, 'maintenance', 1, 'Maintenance Key', '', 50, 'changeme', 1, 'string'),
(44, 'MAINTENANCE_LOG_MAXAGE', '30', 'number', 'Maximum age for log entries in days. Set to 0 to keep logs indefinitely.', NULL, 'maintenance', 1, 'Max Log Age', '', 60, '30', 1, 'int'),
(45, 'MAINTENANCE_STAT_MAXAGE', '30', 'number', 'Maximum age for statistics entries in days. Set to 0 to keep statistics indefinitely.', NULL, 'maintenance', 1, 'Max Statistics Age', '', 70, '30', 1, 'int'),
(46, 'MAINTENANCE_ALERT_TOUT', '12', 'number', 'How long in minutes after the last time a client connects should we send an alert? Can be overridden on a per client basis.', NULL, 'maintenance', 1, 'Max Display Timeout', '', 80, '12', 1, 'int'),
(47, 'SHOW_DISPLAY_AS_VNCLINK', '', 'text', 'Turn the display name in display management into a link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.', NULL, 'displays', 1, 'Add a link to the Display name using this format mask?', '', 30, '', 1, 'string'),
(48, 'SHOW_DISPLAY_AS_VNC_TGT', '_top', 'text', 'If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.', NULL, 'displays', 1, 'The target attribute for the above link', '', 40, '_top', 1, 'string'),
(49, 'MAINTENANCE_ALWAYS_ALERT', 'Off', 'dropdown', 'Should Xibo send an email if a display is in an error state every time the maintenance script runs?', 'On|Off', 'maintenance', 1, 'Send repeat Display Timeouts', '', 80, 'Off', 1, 'word'),
(50, 'SCHEDULE_LOOKAHEAD', 'On', 'dropdown', 'Should Xibo send future schedule information to clients?', 'On|Off', 'general', 0, 'Send Schedule in advance?', '', 40, 'On', 1, 'word'),
(51, 'REQUIRED_FILES_LOOKAHEAD', '172800', 'number', 'How many seconds in to the future should the calls to RequiredFiles look?', NULL, 'general', 1, 'Send files in advance?', '', 50, '172800', 1, 'int'),
(52, 'REGION_OPTIONS_COLOURING', 'Media Colouring', 'dropdown', NULL, 'Media Colouring|Permissions Colouring', 'permissions', 1, 'How to colour Media on the Region Timeline', '', 30, 'Media Colouring', 1, 'string'),
(53, 'LAYOUT_COPY_MEDIA_CHECKB', 'Unchecked', 'dropdown', 'Default the checkbox for making duplicates of media when copying layouts', 'Checked|Unchecked', 'defaults', 1, 'Default copy media when copying a layout?', '', 20, 'Unchecked', 1, 'word'),
(54, 'MAX_LICENSED_DISPLAYS', '0', 'number', 'The maximum number of licensed clients for this server installation. 0 = unlimited', NULL, 'displays', 0, 'Number of display slots', '', 50, '0', 0, 'int'),
(55, 'LIBRARY_MEDIA_UPDATEINALL_CHECKB', 'Checked', 'dropdown', 'Default the checkbox for updating media on all layouts when editing in the library', 'Checked|Unchecked', 'defaults', 1, 'Default update media in all layouts', '', 10, 'Unchecked', 1, 'word'),
(56, 'USER_PASSWORD_POLICY', '', 'text', 'Regular Expression for password complexity, leave blank for no policy.', '', 'users', 1, 'Password Policy Regular Expression', '', 20, '', 1, 'string'),
(57, 'USER_PASSWORD_ERROR', '', 'text', 'A text description of this password policy. Will be show to users when their password does not meet the required policy', '', 'users', 1, 'Description of Password Policy', '', 30, '', 1, 'string'),
(58, 'MODULE_CONFIG_LOCKED_CHECKB', 'Unchecked', 'dropdown', 'Is the module config locked? Useful for Service providers.', 'Checked|Unchecked', 'defaults', 0, 'Lock Module Config', '', 30, 'Unchecked', 0, 'word'),
(59, 'LIBRARY_SIZE_LIMIT_KB', '0', 'number', 'The Limit for the Library Size in KB', NULL, 'network', 0, 'Library Size Limit', '', 50, '0', 1, 'int'),
(60, 'MONTHLY_XMDS_TRANSFER_LIMIT_KB', '0', 'number', 'XMDS Transfer Limit in KB/month', NULL, 'network', 0, 'Monthly bandwidth Limit', '', 40, '0', 1, 'int'),
(61, 'DEFAULT_LANGUAGE', 'en_GB', 'text', 'The default language to use', NULL, 'regional', 1, 'Default Language', '', 10, 'en_GB', 1, 'string'),
(62, 'TRANSITION_CONFIG_LOCKED_CHECKB', 'Unchecked', 'dropdown', 'Is the Transition config locked?', 'Checked|Unchecked', 'defaults', 0, 'Allow modifications to the transition configuration?', '', 40, 'Unchecked', 1, 'word'),
(63, 'GLOBAL_THEME_NAME', 'default', 'text', 'The Theme to apply to all pages by default', NULL, 'configuration', 1, 'CMS Theme', '', 30, 'default', 1, 'word'),
(64, 'DEFAULT_LAT', '51.504', 'number', 'The Latitude to apply for any Geo aware Previews', NULL, 'displays', 1, 'Default Latitude', '', 10, '51.504', 1, 'double'),
(65, 'DEFAULT_LONG', '-0.104', 'number', 'The Longitude to apply for any Geo aware Previews', NULL, 'displays', 1, 'Default Longitude', '', 20, '-0.104', 1, 'double'),
(66, 'SCHEDULE_WITH_VIEW_PERMISSION', 'No', 'dropdown', 'Should users with View permissions on displays be allowed to schedule to them?', 'Yes|No', 'permissions', 1, 'Schedule with view permissions?', '', 40, 'No', 1, 'word'),
(67, 'SETTING_IMPORT_ENABLED', '1', 'checkbox', NULL, NULL, 'general', 1, 'Allow Import?', '', 80, '1', 1, 'checkbox'),
(68, 'SETTING_LIBRARY_TIDY_ENABLED', '1', 'checkbox', NULL, NULL, 'general', 1, 'Enable Library Tidy?', '', 90, '1', 1, 'checkbox'),
(69, 'SENDFILE_MODE', 'Off', 'dropdown', 'When a user downloads a file from the library or previews a layout, should we attempt to use Apache X-Sendfile, Nginx X-Accel, or PHP (Off) to return the file from the library?', 'Off|Apache|Nginx', 'general', 1, 'File download mode', '', 60, 'Off', 1, 'word'),
(70, 'EMBEDDED_STATUS_WIDGET', '', 'text', 'HTML to embed in an iframe on the Status Dashboard', NULL, 'general', 0, 'Status Dashboard Widget', '', 70, '', 1, 'htmlstring'),
(71, 'PROXY_HOST', '', 'text', 'The Proxy URL', NULL, 'network', 1, 'Proxy URL', '', 10, '', 1, 'string'),
(72, 'PROXY_PORT', '0', 'number', 'The Proxy Port', NULL, 'network', 1, 'Proxy Port', '', 20, '0', 1, 'int'),
(73, 'PROXY_AUTH', '', 'text', 'The Authentication information for this proxy. username:password', NULL, 'network', 1, 'Proxy Credentials', '', 30, '', 1, 'string'),
(74, 'DATE_FORMAT',  'Y-m-d H:i',  'text',  'The Date Format to use when displaying dates in the CMS.', NULL ,  'regional',  '1',  'Date Format',  'required',  30,  'Y-m-d',  '1',  'string'),
(75, 'DETECT_LANGUAGE',  '1',  'checkbox',  'Detect the browser language?', NULL ,  'regional',  '1',  'Detect Language',  '',  40,  '1',  1,  'checkbox'),
(76, 'DEFAULTS_IMPORTED', '0', 'text', 'Has the default layout been imported?', NULL, 'general', 0, 'Defaults Imported?', 'required', 100, '0', 0, 'checkbox'),
(77, 'FORCE_HTTPS', '0', 'checkbox', 'Force the portal into HTTPS?', NULL, 'network', 1, 'Force HTTPS?', '', 70, '0', 1, 'checkbox'),
(78, 'ISSUE_STS', '0', 'checkbox', 'Add STS to the response headers? Make sure you fully understand STS before turning it on as it will prevent access via HTTP after the first successful HTTPS connection.', NULL, 'network', 1, 'Enable STS?', '', 80, '0', 1, 'checkbox'),
(79, 'STS_TTL', '600', 'text', 'The Time to Live (maxage) of the STS header expressed in seconds.', NULL, 'network', 1, 'STS Time out', '', 90, '600', 1, 'int'),
(81, 'CALENDAR_TYPE', 'Gregorian', 'dropdown', 'Which Calendar Type should the CMS use?', 'Gregorian|Jalali', 'regional', 1, 'Calendar Type', '', 50, 'Gregorian', 1, 'string'),
(82, 'DASHBOARD_LATEST_NEWS_ENABLED', '1', 'checkbox', 'Should the Dashboard show latest news? The address is provided by the theme.', '', 'general', 1, 'Enable Latest News?', '', 110, '1', 1, 'checkbox'),
(83, 'LIBRARY_MEDIA_DELETEOLDVER_CHECKB','Checked','dropdown','Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.','Checked|Unchecked','defaults',1,'Default for "Delete old version of Media" checkbox. Shown when Editing Library Media.', '', 50, 'Unchecked', 1, 'dropdown'),
(84, 'PROXY_EXCEPTIONS', '', 'text', 'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.', '', 'network', 1, 'Proxy Exceptions', '', 32, '', 1, 'text'),
(85, 'INSTANCE_SUSPENDED', '0', 'checkbox', 'Is this instance suspended?', NULL, 'general', 0, 'Instance Suspended', '', 120, '0', 0, 'checkbox'),
(86, 'INHERIT_PARENT_PERMISSIONS', '1', 'checkbox', 'Inherit permissions from Parent when adding a new item?', NULL, 'permissions', 1, 'Inherit permissions', '', 50, '1', 1, 'checkbox'),
(87, 'XMR_ADDRESS', 'tcp://localhost:5555', 'text', 'Please enter the private address for XMR.', NULL, 'displays', 1, 'XMR Private Address', '', 5, 'tcp:://localhost:5555', 1, 'string'),
(88, 'XMR_PUB_ADDRESS', '', 'text', 'Please enter the public address for XMR.', NULL, 'displays', 1, 'XMR Public Address', '', 6, '', 1, 'string'),
(89, 'CDN_URL', '', 'text', 'Content Delivery Network Address for serving file requests to Players', '', 'network', 0, 'CDN Address', '', 33, '', 0, 'string'),
(90, 'ELEVATE_LOG_UNTIL', '1463396415', 'datetime', 'Elevate the log level until this date.', null, 'troubleshooting', 1, 'Elevate Log Until', ' ', 25, '', 1, 'datetime'),
(91, 'RESTING_LOG_LEVEL', 'Error', 'dropdown', 'Set the level of the resting log level. The CMS will revert to this log level after an elevated period ends. In production systems "error" is recommended.', 'Emergency|Alert|Critical|Error', 'troubleshooting', 1, 'Resting Log Level', '', 19, 'error', 1, 'word'),
(92, 'TASK_CONFIG_LOCKED_CHECKB', 'Unchecked', 'dropdown', 'Is the task config locked? Useful for Service providers.', 'Checked|Unchecked', 'defaults', 0, 'Lock Task Config', '', 30, 'Unchecked', 0, 'word'),
(93, 'WHITELIST_LOAD_BALANCERS', '', 'text', 'If the CMS is behind a load balancer, what are the load balancer IP addresses, comma delimited.', '', 'network', 1, 'Whitelist Load Balancers', '', 100, '', 1, 'string'),
(94, 'DEFAULT_LAYOUT', '1', 'text', 'The default layout to assign for new displays and displays which have their current default deleted.', '1', 'displays', 1, 'Default Layout', '', 4, '', 1, 'int'),
(95, 'DISPLAY_PROFILE_STATS_DEFAULT', '0', 'checkbox', NULL, NULL, 'displays', 1, 'Default setting for Statistics Enabled?', '', 70, '0', 1, 'checkbox'),
(96, 'DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', '1', 'checkbox', NULL, NULL, 'displays', 1, 'Enable the option to report the current layout status?', '', 80, '0', 1, 'checkbox'),
(97, 'DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', '1', 'checkbox', NULL, NULL, 'displays', 1, 'Enable the option to set the screenshot interval?', '', 90, '0', 1, 'checkbox'),
(98, 'DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', '200', 'number', 'The default size in pixels for the Display Screenshots', NULL, 'displays', 1, 'Display Screenshot Default Size', '', 100, '200', 1, 'int'),
(99, 'LATEST_NEWS_URL', 'http://xibo.org.uk/feed', 'text', 'RSS/Atom Feed to be displayed on the Status Dashboard', '', 'general', 0, 'Latest News URL', '', 111, '', 0, 'string'),
(100, 'DISPLAY_LOCK_NAME_TO_DEVICENAME', '0', 'checkbox', NULL, NULL, 'displays', 1, 'Lock the Display Name to the device name provided by the Player?', '', 80, '0', 1, 'checkbox'),
(101, 'mail_from_name', '', 'text', 'Mail will be sent under this name', null, 'maintenance', 1, 'Sending email name', '', 45, '', 1, 'string'),
(102, 'SCHEDULE_SHOW_LAYOUT_NAME', '0', 'checkbox', 'If checked then the Schedule will show the Layout for existing events even if the logged in User does not have permission to see that Layout.', null, 'permissions', 1, 'Show event Layout regardless of User permission?', '', 45, '', 1, 'checkbox'),
(103, 'DEFAULT_USERGROUP', '1', 'text', 'The default User Group for new Users', '1', 'users', 1, 'Default User Group', '', 4, '', 1, 'int');

INSERT INTO `usertype` (`usertypeid`, `usertype`) VALUES
(1, 'Super Admin'),
(2, 'Group Admin'),
(3, 'User');

INSERT INTO `user` (`UserID`, `usertypeid`, `UserName`, `UserPassword`, `lastaccessed`, `email`, `homepageId`, `Retired`) VALUES
(1, 1, 'xibo_admin', '21232f297a57a5a743894a0e4a801fc3', NOW(), '', 29, 0);

INSERT INTO `lkusergroup` (`LkUserGroupID`, `GroupID`, `UserID`) VALUES
(1, 3, 1);

INSERT INTO `transition` (`TransitionID`, `Transition`, `Code`, `HasDuration`, `HasDirection`, `AvailableAsIn`, `AvailableAsOut`) VALUES
(1, 'Fade In', 'fadeIn', 1, 0, 0, 0),
(2, 'Fade Out', 'fadeOut', 1, 0, 0, 0),
(3, 'Fly', 'fly', 1, 1, 0, 0);

INSERT INTO `datatype` (`DataTypeID`, `DataType`) VALUES
(1, 'String'),
(2, 'Number'),
(3, 'Date'),
(4, 'External Image'),
(5, 'Library Image');

INSERT INTO `datasetcolumntype` (`DataSetColumnTypeID`, `DataSetColumnType`) VALUES
(1, 'Value'),
(2, 'Formula'),
(3, 'Remote');

INSERT INTO `bandwidthtype` (`bandwidthtypeid`, `name`) VALUES
(1, 'Register'),
(2, 'Required Files'),
(3, 'Schedule'),
(4, 'Get File'),
(5, 'Get Resource'),
(6, 'Media Inventory'),
(7, 'Notify Status'),
(8, 'Submit Stats'),
(9, 'Submit Log'),
(10, 'Blacklist'),
(11, 'Screen Shot');


INSERT INTO `tag` (`tagId`, `tag`) VALUES
(1, 'template'),
(2, 'background'),
(3, 'thumbnail');

INSERT INTO `displayprofile` (`name`, `type`, `config`, `isdefault`, `userid`)
VALUES ('Windows', 'windows', '[]', '1', '1'), ('Android', 'android', '[]', '1', '1'),  ('webOS', 'lg', '[]', '1', '1');

INSERT INTO `permissionentity` (`entityId`, `entity`) VALUES
(1, 'Xibo\\Entity\\Page'),
(3, 'Xibo\\Entity\\DisplayGroup'),
(4, 'Xibo\\Entity\\Media'),
(5, 'Xibo\\Entity\\Campaign'),
(6, 'Xibo\\Entity\\Widget'),
(7, 'Xibo\\Entity\\Region'),
(8, 'Xibo\\Entity\\Playlist'),
(9, 'Xibo\\Entity\\DataSet'),
(10, 'Xibo\\Entity\\Notification'),
(11, 'Xibo\\Entity\\DayPart');

INSERT INTO `oauth_scopes` (id, description) VALUES ('all', 'All access'),('mcaas', 'Media Conversion as a Service');

INSERT INTO `permission` (entityId, groupId, objectId, view, edit, `delete`) VALUES
  (1, 1, 1, 1, 0, 0),
  (1, 1, 13, 1, 0, 0),
  (1, 1, 4, 1, 0, 0),
  (1, 1, 5, 1, 0, 0),
  (1, 1, 3, 1, 0, 0),
  (1, 1, 33, 1, 0, 0),
  (1, 1, 28, 1, 0, 0),
  (1, 1, 32, 1, 0, 0),
  (1, 1, 2, 1, 0, 0),
  (1, 1, 29, 1, 0, 0),
  (1, 1, 11, 1, 0, 0);


INSERT INTO task (taskId, name, class, status, options, schedule, isActive, configFile) VALUES
  (1, 'Daily Maintenance', '\\Xibo\\XTR\\MaintenanceDailyTask', 2, '[]', '0 0 * * * *', 1, '/tasks/maintenance-daily.task'),
  (2, 'Regular Maintenance', '\\Xibo\\XTR\\MaintenanceRegularTask', 2, '[]', '*/5 * * * * *', 1, '/tasks/maintenance-regular.task'),
  (3, 'Email Notifications', '\\Xibo\\XTR\\EmailNotificationsTask', 2, '[]', '*/5 * * * * *', 1, '/tasks/email-notifications.task'),
  (4, 'Stats Archive', '\\Xibo\\XTR\\StatsArchiveTask', 2, '{"periodSizeInDays":"7","maxPeriods":"4"}', '0 0 * * Mon', 0, '/tasks/stats-archiver.task'),
  (5, 'Remove old Notifications', '\\Xibo\\XTR\\NotificationTidyTask', 2, '{"maxAgeDays":"7","systemOnly":"1","readOnly":"0"}', '15 0 * * *', 1, '/tasks/notification-tidy.task'),
  (6, 'Fetch Remote DataSets', '\\Xibo\\XTR\\RemoteDataSetFetchTask', 2, '[]', '30 * * * * *', 1, '/tasks/remote-dataset.task'),
  (7, 'Widget Sync', '\\Xibo\\XTR\\WidgetSyncTask', 2, '[]', '*/3 * * * *', 1, '/tasks/widget-sync.task');


INSERT INTO daypart (name, description, isRetired, userid, startTime, endTime, exceptions, isAlways, isCustom) VALUES
  ('Custom', 'User specifies the from/to date', 0, 1, '', '', '', 0, 1),
  ('Always', 'Event runs always', 0, 1, '', '', '', 1, 0);

INSERT INTO `permission` (entityId, groupId, objectId, view, edit, `delete`)
  SELECT entityId, groupId, dayPartId, 1, 0, 0
  FROM daypart
    CROSS JOIN permissionentity
    CROSS JOIN `group`
  WHERE entity LIKE '%DayPart'
        AND IsEveryone = 1
        AND (isCustom = 1 OR isAlways = 1);