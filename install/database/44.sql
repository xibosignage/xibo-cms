TRUNCATE TABLE help;

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
(11, 'Displaygroup', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Groups'),
(12, 'Layout', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Adding_Layouts'),
(13, 'Layout', 'Background', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Layout_Designer'),
(14, 'Content', 'Assign', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Library'),
(15, 'Layout', 'RegionOptions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#Assigning_Media'),
(16, 'Content', 'AddtoLibrary', 'http://wiki.xibo.org.uk/wiki/Manual:Media'),
(17, 'Display', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Edit'),
(18, 'Display', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Delete'),
(19, 'Displays', 'Groups', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Displays#Groups'),
(20, 'Groups', 'General', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users'),
(21, 'User', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Add'),
(22, 'User', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Delete'),
(23, 'Content', 'Config', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Settings'),
(24, 'LayoutMedia', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Management#Layout_Permissions'),
(25, 'Region', 'Permissions', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Management#Layout_Permissions'),
(26, 'Library', 'Assign', 'http://wiki.xibo.org.uk/wiki/Manual:Layouts:Design#AssigningFromLibrary'),
(27, 'Media', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Media#Delete'),
(28, 'DisplayGroup', 'Add', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Add'),
(29, 'DisplayGroup', 'Edit', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Edit'),
(30, 'DisplayGroup', 'Delete', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Delete'),
(31, 'DisplayGroup', 'Members', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#Members'),
(32, 'DisplayGroup', 'GroupSecurity', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:DisplayGroups#GroupSecurity');

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'MAX_LICENSED_DISPLAYS', '0', 'text', 'The maximum number of licensed clients for this server installation. 0 = unlimited', NULL, 'general', '0');

ALTER TABLE  `setting` CHANGE  `setting`  `setting` VARCHAR( 50 ) NOT NULL;
INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'LIBRARY_MEDIA_UPDATEINALL_CHECKB', 'Unchecked', 'dropdown', 'Default the checkbox for updating media on all layouts when editing in the library', 'Checked|Unchecked', 'default', '1');

ALTER TABLE  `display` ADD  `MacAddress` VARCHAR( 254 ) NULL COMMENT  'Mac Address of the Client',
ADD  `LastChanged` INT NULL COMMENT  'Last time this Mac Address changed';

ALTER TABLE  `display` ADD  `NumberOfMacAddressChanges` INT NOT NULL;

UPDATE `version` SET `app_ver` = '1.3.2', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '44';