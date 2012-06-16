ALTER TABLE `session` CHANGE  `session_id`  `session_id` VARCHAR( 160 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `display` CHANGE `NumberOfMacAddressChanges` `NumberOfMacAddressChanges` INT( 11 ) NOT NULL DEFAULT '0';

ALTER TABLE `display` ADD `LastWakeOnLanCommandSent` INT NULL;

ALTER TABLE `display` ADD `WakeOnLan` TINYINT NOT NULL DEFAULT 0;

ALTER TABLE `display` ADD `WakeOnLanTime` VARCHAR(5) NULL;

ALTER TABLE `display` ADD `BroadCastAddress` VARCHAR(100) NULL,
    ADD `SecureOn` VARCHAR(17) NULL,
    ADD `Cidr` SMALLINT NULL;

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'USER_PASSWORD_POLICY', '', 'text', 'Regular Expression for password complexity, leave blank for no policy.', '', 'permissions', '1');
INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'USER_PASSWORD_ERROR', '', 'text', 'A text description of this password policy. Will be show to users when their password does not meet the required policy', '', 'permissions', '1');

DELETE FROM `help`;

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

RENAME TABLE `lkgroupdg` TO  `lkdisplaygroupgroup`;

ALTER TABLE  `lkdisplaygroupgroup` CHANGE  `LkGroupDGID`  `LkDisplayGroupGroupID` INT( 11 ) NOT NULL AUTO_INCREMENT;

ALTER TABLE  `lkdisplaygroupgroup` ADD  `View` TINYINT NOT NULL DEFAULT  '0',
ADD  `Edit` TINYINT NOT NULL DEFAULT  '0',
ADD  `Del` TINYINT NOT NULL DEFAULT  '0';

DELETE FROM `lkmenuitemgroup`;
DELETE FROM `menuitem`;
DELETE FROM `menu`;

INSERT INTO `menu` (`MenuID`, `Menu`) VALUES
(8, 'Administration Menu'),
(9, 'Advanced Menu'),
(2, 'Dashboard'),
(7, 'Display Menu'),
(6, 'Design Menu'),
(5, 'Library Menu'),
(1, 'Top Nav');

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
(36, 8, 24, NULL, 'Modules', NULL, NULL, 5);

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

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'MODULE_CONFIG_LOCKED_CHECKB', 'Unchecked', 'dropdown', 'Is the module config locked? Useful for Service providers.', 'Checked|Unchecked', 'general', '0');

DELETE FROM `schedule_detail` WHERE DisplayGroupID NOT IN (SELECT DisplayGroupID FROM `displaygroup`);

ALTER TABLE `schedule_detail` ADD FOREIGN KEY (  `DisplayGroupID` ) REFERENCES `displaygroup` (
`DisplayGroupID`
) ON DELETE RESTRICT ON UPDATE RESTRICT ;

CREATE TABLE IF NOT EXISTS `campaign` (
  `CampaignID` int(11) NOT NULL AUTO_INCREMENT,
  `Campaign` varchar(254) NOT NULL,
  `IsLayoutSpecific` tinyint(4) NOT NULL,
  `UserID` int(11) NOT NULL,
  PRIMARY KEY (`CampaignID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `campaign`
  ADD CONSTRAINT `campaign_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);


CREATE TABLE IF NOT EXISTS `lkcampaigngroup` (
  `LkCampaignGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `CampaignID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkCampaignGroupID`),
  KEY `CampaignID` (`CampaignID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `lkcampaigngroup`
  ADD CONSTRAINT `lkcampaigngroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkcampaigngroup_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

CREATE TABLE IF NOT EXISTS `lkcampaignlayout` (
  `LkCampaignLayoutID` int(11) NOT NULL AUTO_INCREMENT,
  `CampaignID` int(11) NOT NULL,
  `LayoutID` int(11) NOT NULL,
  `DisplayOrder` int(11) NOT NULL,
  PRIMARY KEY (`LkCampaignLayoutID`),
  KEY `CampaignID` (`CampaignID`),
  KEY `LayoutID` (`LayoutID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `lkcampaignlayout`
  ADD CONSTRAINT `lkcampaignlayout_ibfk_2` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`),
  ADD CONSTRAINT `lkcampaignlayout_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

INSERT INTO `pages` (`name`, `pagegroupID`) VALUES ('campaign', '3');

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
    SELECT 6, PageId, NULL, 'Campaigns', NULL, NULL, 1
      FROM `pages`
     WHERE name = 'campaign';

ALTER TABLE  `schedule_detail` ADD  `DisplayOrder` INT NOT NULL DEFAULT  '0';

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '45';
