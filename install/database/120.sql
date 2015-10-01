/* TODO: we need to go through these updates and make sure structure & data.sql are correct */

ALTER TABLE  `layout` ADD  `width` DECIMAL NOT NULL ,
ADD  `height` DECIMAL NOT NULL ,
ADD  `backgroundColor` VARCHAR( 25 ) NULL ,
ADD  `schemaVersion` TINYINT NOT NULL;

ALTER TABLE  `layout` ADD  `backgroundzIndex` INT NOT NULL DEFAULT  '1' AFTER  `backgroundColor`;

CREATE TABLE IF NOT EXISTS `permission` (
  `permissionId` int(11) NOT NULL AUTO_INCREMENT,
  `entityId` int(11) NOT NULL,
  `groupId` int(11) NOT NULL,
  `objectId` int(11) NOT NULL,
  `view` tinyint(4) NOT NULL,
  `edit` tinyint(4) NOT NULL,
  `delete` tinyint(4) NOT NULL,
  PRIMARY KEY (`permissionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `permissionentity` (
  `entityId` int(11) NOT NULL,
  `entity` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `permissionentity` ADD PRIMARY KEY(`entityId`);

ALTER TABLE `lkusergroup` ADD UNIQUE (  `GroupID` ,  `UserID` ) ;
ALTER TABLE `lkdisplaydg` ADD UNIQUE (  `DisplayGroupID` ,  `DisplayId` ) ;

ALTER TABLE `pages` DROP FOREIGN KEY  `pages_ibfk_1` ;
ALTER TABLE `pages` DROP `pagegroupID`;

/* Take existing permissions and pull them into the permissions table */
INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
SELECT groupId, 4, NULL, CONCAT(LayoutId, '_', RegionID, '_', MediaID), view, edit, del
  FROM `lklayoutmediagroup`;

DROP TABLE `lklayoutmediagroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
SELECT groupId, 1, campaignId, NULL, view, edit, del
  FROM `lkcampaigngroup`;

DROP TABLE `lkcampaigngroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
  SELECT groupId, 3, NULL, CONCAT(LayoutId, '_', RegionID), view, edit, del
  FROM `lklayoutregiongroup`;

DROP TABLE `lklayoutregiongroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `objectIdString`, `view`, `edit`, `delete`)
  SELECT groupId, 6, mediaId, NULL, view, edit, del
  FROM `lkmediagroup`;

DROP TABLE `lkmediagroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`)
  SELECT groupId, 1, pageId, 1, 0, 0
  FROM `lkpagegroup`;

DROP TABLE `lkpagegroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`)
  SELECT groupId, 2, menuItemId, 1, 0, 0
  FROM `lkmenuitemgroup`;

DROP TABLE `lkmenuitemgroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`)
  SELECT groupId, 2, menuItemId, 1, 0, 0
  FROM `lkdatasetgroup`;

DROP TABLE `lkdatasetgroup`;

INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`)
  SELECT groupId, 2, menuItemId, 1, 0, 0
  FROM `lkdisplaygroupgroup`;

DROP TABLE `lkdisplaygroupgroup`;


/* End permissions swap */

DROP TABLE `lklayoutmedia`;

ALTER TABLE  `log` CHANGE  `type`  `type` VARCHAR( 254 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

UPDATE  `pages` SET  `name` =  'library' WHERE  `pages`.`name` = 'content';
UPDATE  `pages` SET  `name` =  'applications' WHERE  `pages`.`name` = 'oauth';
INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (NULL, 'playlist');
INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (NULL, 'maintenance');

/* Update the home page to be a homePageId */
UPDATE `user` SET homepage = IFNULL((SELECT pageId FROM `pages` WHERE pages.name = `user`.homepage), 1);
ALTER TABLE  `user` CHANGE  `homepage`  `homePageId` INT NOT NULL DEFAULT  '1' COMMENT  'The users homepage';

DELETE FROM module WHERE module = 'counter';

ALTER TABLE `log`
  DROP `scheduleID`,
  DROP `layoutID`,
  DROP `mediaID`;

ALTER TABLE `log`
  DROP `RequestUri`,
  DROP `RemoteAddr`,
  DROP `UserAgent`;

ALTER TABLE  `log` ADD  `channel` VARCHAR( 5 ) NOT NULL AFTER  `logdate`;
ALTER TABLE  `log` ADD  `runNo` VARCHAR( 10 ) NOT NULL AFTER  `logid`;

CREATE TABLE IF NOT EXISTS `lkscheduledisplaygroup` (
  `eventId` int(11) NOT NULL,
  `displayGroupId` int(11) NOT NULL,
  PRIMARY KEY (`eventId`,`displayGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE  `schedule_detail` DROP FOREIGN KEY  `schedule_detail_ibfk_8` ;
ALTER TABLE `schedule_detail` DROP `DisplayGroupID`;
ALTER TABLE `schedule` DROP `DisplayGroupIDs`;


CREATE TABLE IF NOT EXISTS `lkregionplaylist` (
  `regionId` int(11) NOT NULL,
  `playlistId` int(11) NOT NULL,
  `displayOrder` int(11) NOT NULL,
  PRIMARY KEY (`regionId`,`playlistId`,`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lkwidgetmedia` (
  `widgetId` int(11) NOT NULL,
  `mediaId` int(11) NOT NULL,
  PRIMARY KEY (`widgetId`,`mediaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `playlist` (
  `playlistId` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(254) DEFAULT NULL,
  `ownerId` int(11) NOT NULL,
  PRIMARY KEY (`playlistId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=18 ;

CREATE TABLE IF NOT EXISTS `region` (
  `regionId` int(11) NOT NULL AUTO_INCREMENT,
  `layoutId` int(11) NOT NULL,
  `ownerId` int(11) NOT NULL,
  `name` varchar(254) DEFAULT NULL,
  `width` decimal(12,4) NOT NULL,
  `height` decimal(12,4) NOT NULL,
  `top` decimal(12,4) NOT NULL,
  `left` decimal(12,4) NOT NULL,
  `zIndex` smallint(6) NOT NULL,
  PRIMARY KEY (`regionId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=14 ;

CREATE TABLE IF NOT EXISTS `regionoption` (
  `regionId` int(11) NOT NULL,
  `option` varchar(50) NOT NULL,
  `value` text NULL,
  PRIMARY KEY (`regionId`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `widget` (
  `widgetId` int(11) NOT NULL AUTO_INCREMENT,
  `playlistId` int(11) NOT NULL,
  `ownerId` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `duration` int(11) NOT NULL,
  `displayOrder` int(11) NOT NULL,
  PRIMARY KEY (`widgetId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `widgetoption` (
  `widgetId` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `option` varchar(254) NOT NULL,
  `value` text NULL,
  PRIMARY KEY (`widgetId`,`type`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE  `module` ADD  `viewPath` VARCHAR( 254 ) NOT NULL DEFAULT  '../modules';

DELETE FROM `setting` WHERE setting = 'USE_INTL_DATEFORMAT';

DROP TABLE `oauth_log`, `oauth_server_nonce`, `oauth_server_token`, `oauth_server_registry`;


--
-- Table structure for table `oauth_access_tokens`
--

CREATE TABLE IF NOT EXISTS `oauth_access_tokens` (
  `access_token` varchar(254) NOT NULL,
  `session_id` int(10) unsigned NOT NULL,
  `expire_time` int(11) NOT NULL,
  PRIMARY KEY (`access_token`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_access_token_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_access_token_scopes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `access_token` varchar(254) NOT NULL,
  `scope` varchar(254) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `access_token` (`access_token`),
  KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_codes`
--

CREATE TABLE IF NOT EXISTS `oauth_auth_codes` (
  `auth_code` varchar(254) NOT NULL,
  `session_id` int(10) unsigned NOT NULL,
  `expire_time` int(11) NOT NULL,
  `client_redirect_uri` varchar(500) NOT NULL,
  PRIMARY KEY (`auth_code`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_code_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_auth_code_scopes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `auth_code` varchar(254) NOT NULL,
  `scope` varchar(254) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_code` (`auth_code`),
  KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

CREATE TABLE IF NOT EXISTS `oauth_clients` (
  `id` varchar(254) NOT NULL,
  `secret` varchar(254) NOT NULL,
  `name` varchar(254) NOT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_client_redirect_uris`
--

CREATE TABLE IF NOT EXISTS `oauth_client_redirect_uris` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(254) NOT NULL,
  `redirect_uri` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_refresh_tokens`
--

CREATE TABLE IF NOT EXISTS `oauth_refresh_tokens` (
  `refresh_token` varchar(254) NOT NULL,
  `expire_time` int(11) NOT NULL,
  `access_token` varchar(254) NOT NULL,
  PRIMARY KEY (`refresh_token`),
  KEY `access_token` (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_scopes` (
  `id` varchar(254) NOT NULL,
  `description` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_sessions`
--

CREATE TABLE IF NOT EXISTS `oauth_sessions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(254) NOT NULL,
  `owner_id` varchar(254) NOT NULL,
  `client_id` varchar(254) NOT NULL,
  `client_redirect_uri` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_session_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_session_scopes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `scope` varchar(254) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `oauth_access_tokens`
--
ALTER TABLE `oauth_access_tokens`
ADD CONSTRAINT `oauth_access_tokens_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `oauth_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_access_token_scopes`
--
ALTER TABLE `oauth_access_token_scopes`
ADD CONSTRAINT `oauth_access_token_scopes_ibfk_1` FOREIGN KEY (`access_token`) REFERENCES `oauth_access_tokens` (`access_token`) ON DELETE CASCADE,
ADD CONSTRAINT `oauth_access_token_scopes_ibfk_2` FOREIGN KEY (`scope`) REFERENCES `oauth_scopes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_auth_codes`
--
ALTER TABLE `oauth_auth_codes`
ADD CONSTRAINT `oauth_auth_codes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `oauth_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_auth_code_scopes`
--
ALTER TABLE `oauth_auth_code_scopes`
ADD CONSTRAINT `oauth_auth_code_scopes_ibfk_1` FOREIGN KEY (`auth_code`) REFERENCES `oauth_auth_codes` (`auth_code`) ON DELETE CASCADE,
ADD CONSTRAINT `oauth_auth_code_scopes_ibfk_2` FOREIGN KEY (`scope`) REFERENCES `oauth_scopes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_refresh_tokens`
--
ALTER TABLE `oauth_refresh_tokens`
ADD CONSTRAINT `oauth_refresh_tokens_ibfk_1` FOREIGN KEY (`access_token`) REFERENCES `oauth_access_tokens` (`access_token`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_sessions`
--
ALTER TABLE `oauth_sessions`
ADD CONSTRAINT `oauth_sessions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `oauth_clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `oauth_session_scopes`
--
ALTER TABLE `oauth_session_scopes`
ADD CONSTRAINT `oauth_session_scopes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `oauth_sessions` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `oauth_session_scopes_ibfk_2` FOREIGN KEY (`scope`) REFERENCES `oauth_scopes` (`id`) ON DELETE CASCADE;


UPDATE  `pages` SET  `name` =  'application' WHERE  `pages`.`pageID` =33;

ALTER TABLE  `pages` ADD  `title` VARCHAR( 50 ) NOT NULL;
ALTER TABLE  `pages` ADD  `asHome` TINYINT NOT NULL DEFAULT '0';

DROP TABLE `menuitem`, `menu`;

UPDATE `setting` SET options = 'Emergency|Alert|Critical|Error|Warning|Notice|Info|Debug', value = 'Error' WHERE setting = 'audit';

/* Module classnames */
ALTER TABLE  `module` ADD  `class` VARCHAR( 254 ) NOT NULL;

ALTER TABLE  `lkmediadisplaygroup` ADD UNIQUE (
  `mediaid` ,
  `displaygroupid`
);

ALTER TABLE  `lkcampaignlayout` ADD UNIQUE (
  `CampaignID` ,
  `LayoutID` ,
  `DisplayOrder`
);

/* TODO CLASS column on Module table */


RENAME TABLE `xmdsnonce` TO `requiredfile` ;
ALTER TABLE  `requiredfile` CHANGE  `nonceId`  `rfId` BIGINT( 20 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE `requiredfile` DROP `fileId`;
ALTER TABLE  `requiredfile` CHANGE  `regionId`  `regionId` INT NULL;
ALTER TABLE  `requiredfile` ADD  `requestKey` VARCHAR( 10 ) NOT NULL;
ALTER TABLE  `requiredfile` ADD  `bytesRequested` BIGINT NOT NULL;
ALTER TABLE  `requiredfile` ADD  `complete` TINYINT NOT NULL;
ALTER TABLE `display` DROP `MediaInventoryXml`;
DROP TABLE  `file`;

INSERT INTO `setting` (`settingid`, `setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`)
VALUES (NULL, 'INSTANCE_SUSPENDED', '0', 'checkbox', 'Is this instance suspended?', NULL, 'general', '0', 'Instance Suspended', '', '120', '0', '0', 'checkbox');

INSERT INTO `setting` (`settingid`, `setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`)
VALUES (NULL, 'INHERIT_PARENT_PERMISSIONS', '1', 'checkbox', 'Inherit permissions from Parent when adding a new item?', NULL, 'permissions', '1', 'Inherit permissions', '', '50', '1', '1', 'checkbox');

UPDATE  `setting` SET  `options` =  'private|group|public' WHERE  `setting`.`setting` = 'MEDIA_DEFAULT';
UPDATE  `setting` SET  `options` =  'private|group|public' WHERE  `setting`.`setting` = 'LAYOUT_DEFAULT';

INSERT INTO `datatype` (`DataTypeID`, `DataType`) VALUES ('5', 'Library Image');
UPDATE  `datatype` SET  `DataType` =  'External Image' WHERE  `datatype`.`DataTypeID` =4 AND  `datatype`.`DataType` =  'Image' LIMIT 1 ;

ALTER TABLE  `oauth_clients` ADD  `userId` INT NOT NULL;
ALTER TABLE  `oauth_clients` ADD  `authCode` TINYINT NOT NULL ,
ADD  `clientCredentials` TINYINT NOT NULL;

UPDATE `version` SET `app_ver` = '1.8.0-alpha', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '120';