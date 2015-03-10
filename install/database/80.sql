
ALTER TABLE  `module` ADD  `render_as` VARCHAR( 10 ) NULL;
ALTER TABLE  `module` ADD  `settings` TEXT NULL;

ALTER TABLE  `resolution` ADD  `version` TINYINT NOT NULL DEFAULT  '1';
ALTER TABLE  `resolution` ADD  `enabled` TINYINT NOT NULL DEFAULT  '1';
ALTER TABLE  `resolution` CHANGE  `resolution`  `resolution` VARCHAR( 254 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

UPDATE `resolution` SET enabled = 0;

INSERT INTO `resolution` (`resolution`, `width`, `height`, `intended_width`, `intended_height`, `version`, `enabled`) VALUES
('1080p HD Landscape', 800, 450, 1920, 1080, 2, 1),
('720p HD Landscape', 800, 450, 1280, 720, 2, 1),
('1080p HD Portrait', 450, 800, 1080, 1920, 2, 1),
('720p HD Portrait', 450, 800, 720, 1280, 2, 1),
('4k', 800, 450, 4096, 2304, 2, 1),
('Common PC Monitor 4:3', 800, 600, 1024, 768, 2, 1);

DELETE FROM `lktemplategroup` WHERE TemplateID IN (SELECT TemplateID FROM `template` WHERE isSystem = 1);
DELETE FROM `template` WHERE isSystem = 1;

ALTER TABLE `template` DROP `isSystem`;

ALTER TABLE  `display` ADD  `displayprofileid` INT NULL;

INSERT INTO `pages` (`name`, `pagegroupID`)
SELECT 'displayprofile', pagegroupID FROM `pagegroup` WHERE pagegroup.pagegroup = 'Displays';

INSERT INTO `menuitem` (MenuID, PageID, Args, Text, Class, Img, Sequence, External)
SELECT 7, PageID, NULL, 'Display Settings', NULL, NULL, 4, 0
  FROM `pages`
 WHERE name = 'displayprofile';

CREATE TABLE IF NOT EXISTS `displayprofile` (
  `displayprofileid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` varchar(15) NOT NULL,
  `config` text NOT NULL,
  `isdefault` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  PRIMARY KEY (`displayprofileid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE `layout` ADD `backgroundImageId` INT( 11 ) NULL DEFAULT NULL;
UPDATE layout SET backgroundImageId = SUBSTRING_INDEX(background, '.', 1) WHERE IFNULL(background, '') <> '' AND background LIKE '%.%';
ALTER TABLE  `layout` DROP  `background`;

INSERT INTO `lklayoutmedia` (mediaid, layoutid, regionid)
SELECT backgroundimageid, layoutid, 'background' FROM `layout` INNER JOIN `media` ON media.mediaid = layout.backgroundImageId WHERE IFNULL(backgroundImageId, 0) <> 0;

ALTER TABLE  `setting` CHANGE  `type`  `fieldType` VARCHAR( 24 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE  `setting` ADD  `title` VARCHAR( 254 ) NOT NULL ,
ADD  `type` VARCHAR( 50 ) NOT NULL,
ADD  `validation` VARCHAR( 50 ) NOT NULL ,
ADD  `ordering` INT NOT NULL,
ADD  `default` VARCHAR( 1000 ) NOT NULL,
ADD  `userSee` TINYINT NOT NULL DEFAULT  '1';

UPDATE `setting` SET type = fieldType;

DELETE FROM `setting` WHERE setting IN ('BASE_URL', 'adminMessage', 'ppt_width', 'ppt_height');

UPDATE `setting` SET cat = 'configuration', ordering = 10, usersee = '1', userchange = '1', `default` = '', `title` = 'Library Location' WHERE setting = 'LIBRARY_LOCATION';
UPDATE `setting` SET cat = 'configuration', ordering = 20, usersee = '1', userchange = '1', `default` = '', `title` = 'CMS Secret Key' WHERE setting = 'SERVER_KEY';
UPDATE `setting` SET cat = 'configuration', ordering = 30, usersee = '1', userchange = '1', `default` = 'default', `title` = 'CMS Theme' WHERE setting = 'GLOBAL_THEME_NAME';
UPDATE `setting` SET cat = 'content', ordering = 10, usersee = '1', userchange = '1', `default` = '10', `title` = 'Default PowerPoint Duration' WHERE setting = 'ppt_length';
UPDATE `setting` SET cat = 'content', ordering = 20, usersee = '1', userchange = '1', `default` = '10', `title` = 'Default Flash Duration' WHERE setting = 'swf_length';
UPDATE `setting` SET cat = 'content', ordering = 30, usersee = '1', userchange = '1', `default` = '10', `title` = 'Default Image Duration' WHERE setting = 'jpg_length';
UPDATE `setting` SET cat = 'defaults', ordering = 10, usersee = '1', userchange = '1', `default` = 'Unchecked', `title` = 'Default update media in all layouts' WHERE setting = 'LIBRARY_MEDIA_UPDATEINALL_CHECKB';
UPDATE `setting` SET cat = 'defaults', ordering = 20, usersee = '1', userchange = '1', `default` = 'Unchecked', `title` = 'Default copy media when copying a layout?' WHERE setting = 'LAYOUT_COPY_MEDIA_CHECKB';
UPDATE `setting` SET cat = 'defaults', ordering = 30, usersee = '0', userchange = '0', `default` = 'Unchecked', `title` = 'Lock Module Config' WHERE setting = 'MODULE_CONFIG_LOCKED_CHECKB';
UPDATE `setting` SET cat = 'defaults', ordering = 40, usersee = '1', userchange = '0', `default` = 'Unchecked', `title` = 'Allow modifications to the transition configuration?' WHERE setting = 'TRANSITION_CONFIG_LOCKED_CHECKB';
UPDATE `setting` SET cat = 'displays', ordering = 10, usersee = '1', userchange = '1', `default` = '51.504', `title` = 'Default Latitude' WHERE setting = 'DEFAULT_LAT';
UPDATE `setting` SET cat = 'displays', ordering = 20, usersee = '1', userchange = '1', `default` = '-0.104', `title` = 'Default Longitude' WHERE setting = 'DEFAULT_LONG';
UPDATE `setting` SET cat = 'displays', ordering = 30, usersee = '1', userchange = '1', `default` = '', `title` = 'Display a VNC Link?' WHERE setting = 'SHOW_DISPLAY_AS_VNCLINK';
UPDATE `setting` SET cat = 'displays', ordering = 40, usersee = '1', userchange = '1', `default` = '_top', `title` = 'Open VNC Link in new window?' WHERE setting = 'SHOW_DISPLAY_AS_VNC_TGT';
UPDATE `setting` SET cat = 'displays', ordering = 50, usersee = '0', userchange = '0', `default` = '0', `title` = 'Number of display slots' WHERE setting = 'MAX_LICENSED_DISPLAYS';
UPDATE `setting` SET cat = 'general', ordering = 10, usersee = '1', userchange = '1', `default` = 'On', `title` = 'Allow usage tracking?' WHERE setting = 'PHONE_HOME';
UPDATE `setting` SET cat = 'general', ordering = 20, usersee = '0', userchange = '0', `default` = '', `title` = 'Phone home key' WHERE setting = 'PHONE_HOME_KEY';
UPDATE `setting` SET cat = 'general', ordering = 30, usersee = '0', userchange = '0', `default` = '0', `title` = 'Phone home time' WHERE setting = 'PHONE_HOME_DATE';
UPDATE `setting` SET cat = 'general', ordering = 40, usersee = '1', userchange = '0', `default` = 'On', `title` = 'Send Schedule in advance?' WHERE setting = 'SCHEDULE_LOOKAHEAD';
UPDATE `setting` SET cat = 'general', ordering = 50, usersee = '1', userchange = '1', `default` = '172800', `title` = 'Send files in advance?' WHERE setting = 'REQUIRED_FILES_LOOKAHEAD';
UPDATE `setting` SET cat = 'general', ordering = 60, usersee = '1', userchange = '1', `default` = 'Off', `title` = 'File download mode' WHERE setting = 'SENDFILE_MODE';
UPDATE `setting` SET cat = 'general', ordering = 70, usersee = '1', userchange = '0', `default` = '', `title` = 'Status Dashboard Widget' WHERE setting = 'EMBEDDED_STATUS_WIDGET';
UPDATE `setting` SET cat = 'general', ordering = 80, usersee = '1', userchange = '1', `default` = '1', `title` = 'Allow Import?' WHERE setting = 'SETTING_IMPORT_ENABLED';
UPDATE `setting` SET cat = 'general', ordering = 90, usersee = '1', userchange = '1', `default` = '1', `title` = 'Enable Library Tidy?' WHERE setting = 'SETTING_LIBRARY_TIDY_ENABLED';
UPDATE `setting` SET cat = 'general', ordering = 10, usersee = '1', userchange = '1', `default` = 'http://www.xibo.org.uk/manual/en/', `title` = 'Location of the Manual' WHERE setting = 'HELP_BASE';
UPDATE `setting` SET cat = 'maintenance', ordering = 10, usersee = '1', userchange = '1', `default` = 'Off', `title` = 'Enable Maintenance?' WHERE setting = 'MAINTENANCE_ENABLED';
UPDATE `setting` SET cat = 'maintenance', ordering = 20, usersee = '1', userchange = '1', `default` = 'On', `title` = 'Enable Email Alerts?' WHERE setting = 'MAINTENANCE_EMAIL_ALERTS';
UPDATE `setting` SET cat = 'maintenance', ordering = 30, usersee = '1', userchange = '1', `default` = 'mail@yoursite.com', `title` = 'Admin email address' WHERE setting = 'mail_to';
UPDATE `setting` SET cat = 'maintenance', ordering = 40, usersee = '1', userchange = '1', `default` = 'mail@yoursite.com', `title` = 'Sending email address' WHERE setting = 'mail_from';
UPDATE `setting` SET cat = 'maintenance', ordering = 50, usersee = '1', userchange = '1', `default` = 'changeme', `title` = 'Maintenance Key' WHERE setting = 'MAINTENANCE_KEY';
UPDATE `setting` SET cat = 'maintenance', ordering = 60, usersee = '1', userchange = '1', `default` = '30', `title` = 'Max Log Age' WHERE setting = 'MAINTENANCE_LOG_MAXAGE';
UPDATE `setting` SET cat = 'maintenance', ordering = 70, usersee = '1', userchange = '1', `default` = '30', `title` = 'Max Statistics Age' WHERE setting = 'MAINTENANCE_STAT_MAXAGE';
UPDATE `setting` SET cat = 'maintenance', ordering = 80, usersee = '1', userchange = '1', `default` = '12', `title` = 'Max Display Timeout' WHERE setting = 'MAINTENANCE_ALERT_TOUT';
UPDATE `setting` SET cat = 'maintenance', ordering = 80, usersee = '1', userchange = '1', `default` = 'Off', `title` = 'Send repeat Display Timeouts' WHERE setting = 'MAINTENANCE_ALWAYS_ALERT';
UPDATE `setting` SET cat = 'network', ordering = 10, usersee = '1', userchange = '1', `default` = '', `title` = 'Proxy URL' WHERE setting = 'PROXY_HOST';
UPDATE `setting` SET cat = 'network', ordering = 20, usersee = '1', userchange = '1', `default` = '0', `title` = 'Proxy Port' WHERE setting = 'PROXY_PORT';
UPDATE `setting` SET cat = 'network', ordering = 30, usersee = '1', userchange = '1', `default` = '', `title` = 'Proxy Credentials' WHERE setting = 'PROXY_AUTH';
UPDATE `setting` SET cat = 'network', ordering = 40, usersee = '1', userchange = '0', `default` = '0', `title` = 'Monthly bandwidth Limit' WHERE setting = 'MONTHLY_XMDS_TRANSFER_LIMIT_KB';
UPDATE `setting` SET cat = 'network', ordering = 50, usersee = '1', userchange = '0', `default` = '0', `title` = 'Library Size Limit' WHERE setting = 'LIBRARY_SIZE_LIMIT_KB';
UPDATE `setting` SET cat = 'network', ordering = 60, usersee = '0', userchange = '0', `default` = 'http://www.xibo.org.uk/stats/track.php', `title` = 'Phone home URL' WHERE setting = 'PHONE_HOME_URL';
UPDATE `setting` SET cat = 'permissions', ordering = 10, usersee = '1', userchange = '1', `default` = 'private', `title` = 'Layout Permissions' WHERE setting = 'LAYOUT_DEFAULT';
UPDATE `setting` SET cat = 'permissions', ordering = 20, usersee = '1', userchange = '1', `default` = 'private', `title` = 'Media Permissions' WHERE setting = 'MEDIA_DEFAULT';
UPDATE `setting` SET cat = 'permissions', ordering = 30, usersee = '1', userchange = '1', `default` = 'Media Colouring', `title` = 'How to colour Media on the Region Timeline' WHERE setting = 'REGION_OPTIONS_COLOURING';
UPDATE `setting` SET cat = 'permissions', ordering = 40, usersee = '1', userchange = '1', `default` = 'No', `title` = 'Schedule with view permissions?' WHERE setting = 'SCHEDULE_WITH_VIEW_PERMISSION';
UPDATE `setting` SET cat = 'regional', ordering = 10, usersee = '1', userchange = '1', `default` = 'en_GB', `title` = 'Default Language' WHERE setting = 'DEFAULT_LANGUAGE';
UPDATE `setting` SET cat = 'regional', ordering = 20, usersee = '1', userchange = '1', `default` = 'Europe/London', `title` = 'Timezone' WHERE setting = 'defaultTimezone';
UPDATE `setting` SET cat = 'troubleshooting', ordering = 10, usersee = '1', userchange = '1', `default` = 'Off', `title` = 'Enable Debugging?' WHERE setting = 'debug';
UPDATE `setting` SET cat = 'troubleshooting', ordering = 20, usersee = '1', userchange = '1', `default` = 'Off', `title` = 'Enable Auditing?' WHERE setting = 'audit';
UPDATE `setting` SET cat = 'troubleshooting', ordering = 30, usersee = '1', userchange = '1', `default` = 'Production', `title` = 'Server Mode' WHERE setting = 'SERVER_MODE';
UPDATE `setting` SET cat = 'users', ordering = 0, usersee = '0', userchange = '0', `default` = 'module_user_general.php', `title` = 'User Module' WHERE setting = 'userModule';
UPDATE `setting` SET cat = 'users', ordering = 10, usersee = '1', userchange = '1', `default` = 'User', `title` = 'Default User Type' WHERE setting = 'defaultUsertype';
UPDATE `setting` SET cat = 'users', ordering = 20, usersee = '1', userchange = '1', `default` = '', `title` = 'Password Policy Regular Expression' WHERE setting = 'USER_PASSWORD_POLICY';
UPDATE `setting` SET cat = 'users', ordering = 30, usersee = '1', userchange = '1', `default` = '', `title` = 'Description of Password Policy' WHERE setting = 'USER_PASSWORD_ERROR';

ALTER TABLE  `schedule` ADD  `DisplayOrder` INT NOT NULL DEFAULT '0';

UPDATE `schedule` SET DisplayOrder = IFNULL((SELECT MAX(DisplayOrder) FROM `schedule_detail` WHERE schedule_detail.eventid = schedule.eventid), 0);

ALTER TABLE  `schedule_detail` DROP FOREIGN KEY  `schedule_detail_ibfk_9` ;
ALTER TABLE `schedule_detail` DROP `CampaignID`;
ALTER TABLE `schedule_detail` DROP `is_priority`;
ALTER TABLE `schedule_detail` DROP `DisplayOrder`;

ALTER TABLE  `user` ADD  `newUserWizard` TINYINT NOT NULL DEFAULT  '0';

CREATE TABLE IF NOT EXISTS `xmdsnonce` (
  `nonceId` bigint(20) NOT NULL AUTO_INCREMENT,
  `nonce` varchar(100) NOT NULL,
  `expiry` int(11) NOT NULL,
  `lastUsed` int(11) DEFAULT NULL,
  `displayId` int(11) NOT NULL,
  `fileId` int(11) DEFAULT NULL,
  `size` bigint(20) DEFAULT NULL,
  `storedAs` varchar(100) DEFAULT NULL,
  `layoutId` int(11) DEFAULT NULL,
  `regionId` varchar(100) DEFAULT NULL,
  `mediaId` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`nonceId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE  `schedule` CHANGE  `recurrence_type`  `recurrence_type` ENUM(  'Minute',  'Hour',  'Day',  'Week',  'Month',  'Year' ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE  `display` CHANGE  `client_version`  `client_version` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE  `display` ADD  `currentLayoutId` INT NULL;

CREATE TABLE IF NOT EXISTS `bandwidthtype` (
  `bandwidthtypeid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  PRIMARY KEY (`bandwidthtypeid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=12 ;

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

ALTER TABLE  `display` ADD  `screenShotRequested` TINYINT NOT NULL DEFAULT  '0';

INSERT INTO `help` (`Topic`, `Category`, `Link`) VALUES
('Displayprofile', 'General', 'manual/single.php?p=admin/displayprofiles'),
('DisplayProfile', 'Edit', 'manual/single.php?p=admin/displayprofiles#edit'),
('DisplayProfile', 'Delete', 'manual/single.php?p=admin/displayprofiles#delete');


UPDATE `version` SET `app_ver` = '1.7.0-alpha', `XmdsVersion` = 4, `XlfVersion` = 2 ;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '80';
