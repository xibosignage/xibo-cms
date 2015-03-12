CREATE TABLE IF NOT EXISTS `tag` (
  `tagId` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) NOT NULL,
  PRIMARY KEY (`tagId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

INSERT INTO `tag` (`tagId`, `tag`) VALUES
(1, 'template'),
(2, 'background'),
(3, 'thumbnail');

CREATE TABLE IF NOT EXISTS `lktaglayout` (
  `lkTagLayoutId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `layoutId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagLayoutId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lktagmedia` (
  `lkTagMediaId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `mediaId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagMediaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

/* Clear the un-used template id field */
UPDATE `layout` SET templateID = NULL;

/* Insert into layout, populating the template id */
INSERT INTO `layout` (layout, xml, userID, createdDT, modifiedDT, description, tags, retired, templateID)
SELECT template, xml, userID, createdDT, modifiedDT, description, tags, retired, templateID
  FROM template;

/* Add the template tag */
INSERT INTO lktaglayout (tagId, layoutId)
SELECT 1, layoutId
  FROM `layout`
 WHERE templateId IS NOT NULL;

/* Add two temporary columns to the campaign table */
ALTER TABLE  `campaign` ADD  `layoutId` INT NOT NULL ,
	ADD  `templateId` INT NOT NULL;

/* Insert into campaign */
INSERT INTO `campaign` (campaign, islayoutspecific, userid, layoutid, templateid)
SELECT layout, 1, userid, layoutid, templateId
  FROM layout
 WHERE templateId IS NOT NULL;

/* Link up */
INSERT INTO `lkcampaignlayout` (CampaignID, LayoutId, DisplayOrder)
SELECT CampaignID, LayoutId, 0
  FROM `campaign`
 WHERE templateid <> 0;

/* Permissions */
INSERT INTO `lkcampaigngroup` (CampaignID, GroupID, View, Edit, Del)
SELECT campaign.CampaignID, groupid, view, edit, del
  FROM lktemplategroup
  	INNER JOIN campaign
  	ON lktemplategroup.templateid = campaign.templateid;

/* Tidy up */
ALTER TABLE `campaign`
  DROP `layoutId`,
  DROP `templateId`;

DROP TABLE  `lktemplategroup`;
DROP TABLE  `template`;

ALTER TABLE `layout` DROP `templateID`;

/* Tags from the layout table */
INSERT INTO tag (tag)
SELECT tags
  FROM (
    SELECT DISTINCT
      SUBSTRING_INDEX(SUBSTRING_INDEX(layout.tags, ' ', numbers.n), ' ', -1) tags
    FROM
      (
          SELECT 1 n  UNION ALL 
          SELECT 2 UNION ALL 
          SELECT 3  UNION ALL 
          SELECT 4  UNION ALL 
          SELECT 5  UNION ALL 
          SELECT 6  UNION ALL 
          SELECT 7  UNION ALL 
          SELECT 8  UNION ALL 
          SELECT 9  UNION ALL 
          SELECT 10  UNION ALL 
          SELECT 11 UNION ALL 
          SELECT 12 UNION ALL 
          SELECT 13 UNION ALL 
          SELECT 14 UNION ALL 
          SELECT 15
      ) numbers INNER JOIN layout
      ON CHAR_LENGTH(layout.tags)
         -CHAR_LENGTH(REPLACE(layout.tags, ' ', ''))>=numbers.n-1
    ORDER BY
      layoutid, n
    ) tags
 WHERE tags.tags NOT IN (SELECT tag FROM tag);

INSERT INTO lktaglayout (tagId, layoutId)
SELECT tagid, layoutid
  FROM tag
    INNER JOIN (
        SELECT
          layout.layoutid,
          SUBSTRING_INDEX(SUBSTRING_INDEX(layout.tags, ' ', numbers.n), ' ', -1) tag
        FROM
          (
              SELECT 1 n  UNION ALL 
              SELECT 2 UNION ALL 
              SELECT 3  UNION ALL 
              SELECT 4  UNION ALL 
              SELECT 5  UNION ALL 
              SELECT 6  UNION ALL 
              SELECT 7  UNION ALL 
              SELECT 8  UNION ALL 
              SELECT 9  UNION ALL 
              SELECT 10  UNION ALL 
              SELECT 11 UNION ALL 
              SELECT 12 UNION ALL 
              SELECT 13 UNION ALL 
              SELECT 14 UNION ALL 
              SELECT 15
          ) numbers INNER JOIN layout
          ON CHAR_LENGTH(layout.tags)
             -CHAR_LENGTH(REPLACE(layout.tags, ' ', ''))>=numbers.n-1
        ORDER BY
          layoutid, n
    ) tagFromLayout
    ON tagFromLayout.tag = tag.tag;

ALTER TABLE `layout` DROP `tags`;

INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES ('DEFAULTS_IMPORTED',  '0',  'text',  'Has the default layout been imported?', NULL ,  'general',  '0',  'Defaults Imported?',  'required',  '100',  '0',  '0',  'checkbox');

ALTER TABLE  `display` CHANGE  `Cidr`  `Cidr` VARCHAR( 6 ) NULL DEFAULT NULL;

TRUNCATE TABLE `help`;

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

INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
 'FORCE_HTTPS',  '0', 'checkbox',  'Force the portal into HTTPS?', NULL ,  'network',  '1',  'Force HTTPS?',  '',  '70',  '0',  '1',  'checkbox'
),(
 'ISSUE_STS',  '0', 'checkbox',  'Add STS to the response headers? Make sure you fully understand STS before turning it on as it will prevent access via HTTP after the first successful HTTPS connection.', NULL ,  'network',  '1',  'Enable STS?',  '',  '80',  '0',  '1',  'checkbox'
),(
 'STS_TTL',  '600', 'text',  'The Time to Live (maxage) of the STS header expressed in minutes.', NULL ,  'network',  '1',  'STS Time out',  '',  '90',  '600',  '1',  'int'
),(
  'MAINTENANCE_ALERTS_FOR_VIEW_USERS', '0', 'checkbox', 'Email maintenance alerts for users with view permissions to effected Displays.', NULL, 'displays', '1', 'Maintenance Alerts for Users', '', '60', '0', '1', 'checkbox'
);

ALTER TABLE  `media` ADD  `valid` TINYINT( 1 ) NOT NULL DEFAULT  '1';
ALTER TABLE  `media` ADD  `expires` INT NULL;

INSERT INTO `datatype` (`DataTypeID`, `DataType`) VALUES ('4', 'Image');

DELETE FROM `module` WHERE module = 'Microblog' LIMIT 1;

UPDATE  `setting` SET  `options` =  'error|info|audit|off', `default` =  'error', `title` = 'Log Level', `helptext` =  'Set the level of logging the CMS should record. In production systems "error" is recommended.' WHERE  `setting`.`setting` = 'audit';
DELETE FROM `setting` WHERE setting = 'debug';

UPDATE `version` SET `app_ver` = '1.7.0-beta', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '82';