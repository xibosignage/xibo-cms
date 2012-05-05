ALTER TABLE  `session` CHANGE  `session_id`  `session_id` VARCHAR( 160 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `display` CHANGE `NumberOfMacAddressChanges` `NumberOfMacAddressChanges` INT( 11 ) NOT NULL DEFAULT '0';

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
(70, 'Services', 'Log', 'http://wiki.xibo.org.uk/wiki/Manual:Applications#View_Log');

RENAME TABLE `lkgroupdg` TO  `xibo_133`.`lkdisplaygroupgroup`;

ALTER TABLE  `lkdisplaygroupgroup` CHANGE  `LkGroupDGID`  `LkDisplayGroupGroupID` INT( 11 ) NOT NULL AUTO_INCREMENT;

ALTER TABLE  `lkdisplaygroupgroup` ADD  `View` TINYINT NOT NULL DEFAULT  '0',
ADD  `Edit` TINYINT NOT NULL DEFAULT  '0',
ADD  `Del` TINYINT NOT NULL DEFAULT  '0';

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '45';