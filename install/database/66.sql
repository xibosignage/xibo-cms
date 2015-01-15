DELETE FROM `help`;

INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) VALUES
(1, 'Layout', 'General', 'manual/single.php?p=layout/overview'),
(2, 'Content', 'General', 'manual/single.php?p=content/overview'),
(4, 'Schedule', 'General', 'manual/single.php?p=schedule/overview'),
(5, 'Group', 'General', 'manual/single.php?p=users/groups'),
(6, 'Admin', 'General', 'manual/single.php?p=admin/settings'),
(7, 'Report', 'General', 'manual/single.php?p=admin/advanced'),
(8, 'Dashboard', 'General', 'manual/single.php?p=coreconcepts/dashboard'),
(9, 'User', 'General', 'manual/single.php?p=users/users'),
(10, 'Display', 'General', 'manual/single.php?p=admin/displays'),
(11, 'DisplayGroup', 'General', 'manual/single.php?p=admin/displaygroups'),
(12, 'Layout', 'Add', 'manual/single.php?p=layout/overview#Add_Layout'),
(13, 'Layout', 'Background', 'manual/single.php?p=layout/layoutdesigner#Background'),
(14, 'Content', 'Assign', 'manual/single.php?p=layout/assigncontent#Assigning_Content'),
(15, 'Layout', 'RegionOptions', 'manual/single.php?p=layout/assigncontent'),
(16, 'Content', 'AddtoLibrary', 'manual/single.php?p=content/adding'),
(17, 'Display', 'Edit', 'manual/single.php?p=admin/displays#Display_Edit'),
(18, 'Display', 'Delete', 'manual/single.php?p=admin/displays#Display_Delete'),
(19, 'Displays', 'Groups', 'manual/single.php?p=admin/displaygroups#Group_Members'),
(20, 'UserGroup', 'Add', 'manual/single.php?p=users/groups#Adding_Group'),
(21, 'User', 'Add', 'manual/single.php?p=users/users#Add_User'),
(22, 'User', 'Delete', 'manual/single.php?p=users/users#Delete_User'),
(23, 'Content', 'Config', 'manual/single.php?p=admin/settings#Content'),
(24, 'LayoutMedia', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(25, 'Region', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(26, 'Library', 'Assign', 'manual/single.php?p=layout/assigncontent#Add_From_Library'),
(27, 'Media', 'Delete', 'manual/single.php?p=content/deleting'),
(28, 'DisplayGroup', 'Add', 'manual/single.php?p=admin/displaygroups#Add_Group'),
(29, 'DisplayGroup', 'Edit', 'manual/single.php?p=admin/displaygroups#Edit_Group'),
(30, 'DisplayGroup', 'Delete', 'manual/single.php?p=admin/displaygroups#Delete_Group'),
(31, 'DisplayGroup', 'Members', 'manual/single.php?p=admin/displaygroups#Group_Members'),
(32, 'DisplayGroup', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(34, 'Schedule', 'ScheduleNow', 'manual/single.php?p=schedule/schedule_now'),
(35, 'Layout', 'Delete', 'manual/single.php?p=layout/overview#Delete_Layout'),
(36, 'Layout', 'Copy', 'manual/single.php?p=layout/overview#Copy_Layout'),
(37, 'Schedule', 'Edit', 'manual/single.php?p=schedule/schedule_event'),
(38, 'Schedule', 'Add', 'manual/single.php?p=schedule/schedule_event'),
(39, 'Layout', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(40, 'Display', 'MediaInventory', 'manual/single.php?p=admin/displays#Media_Inventory'),
(41, 'User', 'ChangePassword', 'manual/single.php?p=coreconcepts/navbar#Change_Password'),
(42, 'Schedule', 'Delete', 'manual/single.php?p=schedule/schedule_event'),
(43, 'Layout', 'Edit', 'manual/single.php?p=layout/overview#Edit_Layout'),
(44, 'Media', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(45, 'Display', 'DefaultLayout', 'manual/single.php?p=admin/displays'),
(46, 'UserGroup', 'Edit', 'manual/single.php?p=users/groups#Edit_Group'),
(47, 'UserGroup', 'Members', 'manual/single.php?p=users/groups#Group_Member'),
(48, 'User', 'PageSecurity', 'manual/single.php?p=users/menu_page_security#Page_Security'),
(49, 'User', 'MenuSecurity', 'manual/single.php?p=users/menu_page_security#Menu_Security'),
(50, 'UserGroup', 'Delete', 'manual/single.php?p=users/groups#Delete_Group'),
(51, 'User', 'Edit', 'manual/single.php?p=users/users#Edit_User'),
(52, 'User', 'Applications', 'manual/single.php?p=users/users#Users_MyApplications'),
(53, 'User', 'SetHomepage', 'manual/single.php?p=coreconcepts/dashboard#Media_Dashboard'),
(54, 'DataSet', 'General', 'manual/single.php?p=content/content_dataset'),
(55, 'DataSet', 'Add', 'manual/single.php?p=content/content_dataset#Create_Dataset'),
(56, 'DataSet', 'Edit', 'manual/single.php?p=content/content_dataset#Edit_Dataset'),
(57, 'DataSet', 'Delete', 'manual/single.php?p=content/content_dataset#Delete_Dataset'),
(58, 'DataSet', 'AddColumn', 'manual/single.php?p=content/content_dataset#Dataset_Column'),
(59, 'DataSet', 'EditColumn', 'manual/single.php?p=content/content_dataset#Dataset_Column'),
(60, 'DataSet', 'DeleteColumn', 'manual/single.php?p=content/content_dataset#Dataset_Column'),
(61, 'DataSet', 'Data', 'manual/single.php?p=content/content_dataset#Dataset_Row'),
(62, 'DataSet', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(63, 'Fault', 'General', 'manual/single.php?p=admin/advanced#Report_Fault'),
(65, 'Stats', 'General', 'manual/single.php?p=admin/displaystats'),
(66, 'Resolution', 'General', 'manual/single.php?p=templates/template_resolution'),
(67, 'Template', 'General', 'manual/single.php?p=templates/overview'),
(68, 'Services', 'Register', 'manual/single.php?p=admin/api_oauth#Registered_Applications'),
(69, 'OAuth', 'General', 'manual/single.php?p=admin/api_oauth'),
(70, 'Services', 'Log', 'manual/single.php?p=admin/api_oauth#oAuthLog'),
(71, 'Module', 'Edit', 'manual/single.php?p=admin/modules'),
(72, 'Module', 'General', 'manual/single.php?p=admin/modules'),
(73, 'Campaign', 'General', 'manual/single.php?p=layout/campaign_layout'),
(74, 'License', 'General', 'manual/single.php?p=license/licenses'),
(75, 'DataSet', 'ViewColumns', 'manual/single.php?p=content/content_dataset#Dataset_Column'),
(76, 'Campaign', 'Permissions', 'manual/single.php?p=users/user_permissions'),
(77, 'Transition', 'Edit', 'manual/single.php?p=layout/transitions'),
(78, 'User', 'SetPassword', 'manual/single.php?p=users/users#Set_Password'),
(79, 'DataSet', 'ImportCSV', 'manual/single.php?p=content/content_dataset#Import_CSV'),
(80, 'Statusdashboard', 'General', 'manual/single.php?p=coreconcepts/dashboard#Status_Dashboard');

INSERT INTO  `setting` (
`settingid` ,
`setting` ,
`value` ,
`type` ,
`helptext` ,
`options` ,
`cat` ,
`userChange`
)
VALUES (
NULL ,  'SETTING_IMPORT_ENABLED',  'On',  'dropdown', NULL ,  'On|Off',  'general',  '1'
), (
NULL ,  'SETTING_LIBRARY_TIDY_ENABLED',  'On',  'dropdown', NULL ,  'On|Off',  'general',  '1'
), (
NULL, 'SENDFILE_MODE', 'Off', 'dropdown', 'When a user downloads a file from the library or previews a layout, should we attempt to use Apache X-Sendfile, Nginx X-Accel, or PHP (Off) to return the file from the library?', 'Off|Apache|Nginx', 'general', '1');

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'EMBEDDED_STATUS_WIDGET', '', 'text', 'HTML to embed in an iframe on the Status Dashboard', NULL, 'general', '0');

ALTER TABLE  `setting` CHANGE  `value`  `value` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

INSERT INTO pages (name, pagegroupid)
SELECT 'statusdashboard', (SELECT pagegroupid FROM pagegroup WHERE pagegroup = 'Homepage and Login')
UNION ALL
SELECT 'preview', (SELECT pagegroupid FROM pagegroup WHERE pagegroup = 'Layouts');

INSERT INTO `lkpagegroup` (`pageID`, `groupID`)
SELECT pageID, 1 FROM pages WHERE name = 'preview' OR name = 'statusdashboard' OR name = 'timeline';

UPDATE `version` SET `app_ver` = '1.6.0-rc1', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '66';
