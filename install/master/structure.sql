
--
-- Table structure for table `auditlog`
--

CREATE TABLE IF NOT EXISTS `auditlog` (
  `logId` int(11) NOT NULL AUTO_INCREMENT,
  `logDate` int(11) NOT NULL,
  `userId` int(11) NULL,
  `message` varchar(254) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `entityId` int(11) NOT NULL,
  `objectAfter` text NOT NULL,
  PRIMARY KEY (`logId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `bandwidth`
--

CREATE TABLE IF NOT EXISTS `bandwidth` (
  `DisplayID` int(11) NOT NULL,
  `Type` tinyint(4) NOT NULL,
  `Month` int(11) NOT NULL,
  `Size` bigint(20) NOT NULL,
  PRIMARY KEY (`DisplayID`,`Type`,`Month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `bandwidthtype`
--

CREATE TABLE IF NOT EXISTS `bandwidthtype` (
  `bandwidthtypeid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  PRIMARY KEY (`bandwidthtypeid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=12 ;

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE IF NOT EXISTS `blacklist` (
  `BlackListID` int(11) NOT NULL AUTO_INCREMENT,
  `MediaID` int(11) NOT NULL,
  `DisplayID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL COMMENT 'Null if it came from a display',
  `ReportingDisplayID` int(11) DEFAULT NULL COMMENT 'The display that reported the blacklist',
  `Reason` text NOT NULL,
  `isIgnored` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Ignore this blacklist',
  PRIMARY KEY (`BlackListID`),
  KEY `MediaID` (`MediaID`),
  KEY `DisplayID` (`DisplayID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Blacklisted media will not get sent to the Display' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `campaign`
--

CREATE TABLE IF NOT EXISTS `campaign` (
  `CampaignID` int(11) NOT NULL AUTO_INCREMENT,
  `Campaign` varchar(254) NOT NULL,
  `IsLayoutSpecific` tinyint(4) NOT NULL,
  `UserID` int(11) NOT NULL,
  PRIMARY KEY (`CampaignID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `dataset`
--

CREATE TABLE IF NOT EXISTS `dataset` (
  `DataSetID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSet` varchar(50) NOT NULL,
  `Description` varchar(254) DEFAULT NULL,
  `UserID` int(11) NOT NULL,
  `LastDataEdit` int(11) NOT NULL DEFAULT '0',
  `code` varchar(50) DEFAULT NULL,
  `isLookup` tinyint(4) NOT NULL DEFAULT '0',
  isRemote tinyint default '0' not null,
  method enum('GET', 'POST') null,
  uri varchar(250) null,
  postData text null,
  authentication varchar(10) null,
  username varchar(100) null,
  password varchar(250) null,
  refreshRate int default '86400' null,
  clearRate int default '0' null,
  runsAfter int null,
  dataRoot varchar(250) null,
  lastSync int default '0' not null,
  lastClear int default '0' not null,
  summarize varchar(10) null,
  summarizeField varchar(250) null,
  PRIMARY KEY (`DataSetID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `datasetcolumn`
--

CREATE TABLE IF NOT EXISTS `datasetcolumn` (
  `DataSetColumnID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSetID` int(11) NOT NULL,
  `Heading` varchar(50) NOT NULL,
  `DataTypeID` smallint(6) NOT NULL,
  `DataSetColumnTypeID` smallint(6) NOT NULL,
  `ListContent` varchar(1000) DEFAULT NULL,
  `ColumnOrder` smallint(6) NOT NULL,
  `Formula` varchar(1000) DEFAULT NULL,
  `RemoteField` VARCHAR(250) DEFAULT NULL,
  `showFilter` TINYINT(4) DEFAULT 1,
  `showSort` TINYINT(4) DEFAULT 1,
  PRIMARY KEY (`DataSetColumnID`),
  KEY `DataSetID` (`DataSetID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `datasetcolumntype`
--

CREATE TABLE IF NOT EXISTS `datasetcolumntype` (
  `DataSetColumnTypeID` smallint(6) NOT NULL,
  `DataSetColumnType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `datatype`
--

CREATE TABLE IF NOT EXISTS `datatype` (
  `DataTypeID` smallint(6) NOT NULL,
  `DataType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `display`
--

CREATE TABLE IF NOT EXISTS `display` (
  `displayid` int(11) NOT NULL AUTO_INCREMENT,
  `auditingUntil` int(11) NOT NULL DEFAULT '0' COMMENT 'Is this display auditing',
  `display` varchar(50) NOT NULL,
  `defaultlayoutid` int(8) NOT NULL,
  `license` varchar(40) DEFAULT NULL,
  `licensed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is the Requested License Key Allowed',
  `loggedin` tinyint(4) NOT NULL DEFAULT '0',
  `lastaccessed` int(11) DEFAULT NULL,
  `inc_schedule` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Will this default be used in the scheduling calcs',
  `email_alert` tinyint(1) NOT NULL DEFAULT '1',
  `alert_timeout` int(11) NOT NULL DEFAULT '0',
  `ClientAddress` varchar(100) DEFAULT NULL,
  `MediaInventoryStatus` tinyint(4) NOT NULL DEFAULT '0',
  `MacAddress` varchar(254) DEFAULT NULL COMMENT 'Mac Address of the Client',
  `LastChanged` int(11) DEFAULT NULL COMMENT 'Last time this Mac Address changed',
  `NumberOfMacAddressChanges` int(11) NOT NULL DEFAULT '0',
  `LastWakeOnLanCommandSent` int(11) DEFAULT NULL,
  `WakeOnLan` tinyint(4) NOT NULL DEFAULT '0',
  `WakeOnLanTime` varchar(5) DEFAULT NULL,
  `BroadCastAddress` varchar(100) DEFAULT NULL,
  `SecureOn` varchar(17) DEFAULT NULL,
  `Cidr` varchar(6) DEFAULT NULL,
  `GeoLocation` point DEFAULT NULL,
  `version_instructions` varchar(255) DEFAULT NULL,
  `client_type` varchar(20) DEFAULT NULL,
  `client_version` varchar(15) DEFAULT NULL,
  `client_code` smallint(6) DEFAULT NULL,
  `displayprofileid` int(11) DEFAULT NULL,
  `screenShotRequested` tinyint(4) NOT NULL DEFAULT '0',
  `storageAvailableSpace` bigint(20) DEFAULT NULL,
  `storageTotalSpace` bigint(20) DEFAULT NULL,
  `xmrChannel` varchar(254) DEFAULT NULL,
  `xmrPubKey` text,
  `lastCommandSuccess` tinyint(4) NOT NULL DEFAULT '2',
  `deviceName` VARCHAR(254) DEFAULT NULL,
  `timeZone` VARCHAR(254) DEFAULT NULL,
  PRIMARY KEY (`displayid`),
  KEY `defaultplaylistid` (`defaultlayoutid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `displaygroup`
--

CREATE TABLE IF NOT EXISTS `displaygroup` (
  `DisplayGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `DisplayGroup` varchar(50) NOT NULL,
  `Description` varchar(254) DEFAULT NULL,
  `IsDisplaySpecific` tinyint(4) NOT NULL DEFAULT '0',
  `isDynamic` tinyint(4) NOT NULL DEFAULT '0',
  `dynamicCriteria` varchar(2000) DEFAULT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`DisplayGroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `displayprofile`
--

CREATE TABLE IF NOT EXISTS `displayprofile` (
  `displayprofileid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` varchar(15) NOT NULL,
  `config` text NOT NULL,
  `isdefault` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  PRIMARY KEY (`displayprofileid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Table structure for table `group`
--

CREATE TABLE IF NOT EXISTS `group` (
  `groupID` int(11) NOT NULL AUTO_INCREMENT,
  `group` varchar(50) NOT NULL,
  `IsUserSpecific` tinyint(4) NOT NULL DEFAULT '0',
  `IsEveryone` tinyint(4) NOT NULL DEFAULT '0',
  `libraryQuota` int(11) DEFAULT NULL,
  `isSystemNotification` tinyint(4) NOT NULL DEFAULT '0',
  `isDisplayNotification` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`groupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Groups' AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `help`
--

CREATE TABLE IF NOT EXISTS `help` (
  `HelpID` int(11) NOT NULL AUTO_INCREMENT,
  `Topic` varchar(254) NOT NULL,
  `Category` varchar(254) NOT NULL DEFAULT 'General',
  `Link` varchar(254) NOT NULL,
  PRIMARY KEY (`HelpID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `layout`
--

CREATE TABLE IF NOT EXISTS `layout` (
  `layoutID` int(11) NOT NULL AUTO_INCREMENT,
  `layout` varchar(50) NOT NULL,
  `userID` int(11) NOT NULL COMMENT 'The UserID that created this layout',
  `createdDT` datetime NOT NULL,
  `modifiedDT` datetime NOT NULL,
  `description` varchar(254) DEFAULT NULL,
  `tags` varchar(254) DEFAULT NULL,
  `retired` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Is this layout retired',
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT 'The duration in seconds',
  `backgroundImageId` int(11) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `width` decimal(10,0) NOT NULL,
  `height` decimal(10,0) NOT NULL,
  `backgroundColor` varchar(25) DEFAULT NULL,
  `backgroundzIndex` int(11) NOT NULL DEFAULT '1',
  `schemaVersion` tinyint(4) NOT NULL,
  `statusMessage` TEXT NULL,
  PRIMARY KEY (`layoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Layouts' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lkcampaignlayout`
--

CREATE TABLE IF NOT EXISTS `lkcampaignlayout` (
  `LkCampaignLayoutID` int(11) NOT NULL AUTO_INCREMENT,
  `CampaignID` int(11) NOT NULL,
  `LayoutID` int(11) NOT NULL,
  `DisplayOrder` int(11) NOT NULL,
  PRIMARY KEY (`LkCampaignLayoutID`),
  UNIQUE KEY `CampaignID_2` (`CampaignID`,`LayoutID`,`DisplayOrder`),
  KEY `CampaignID` (`CampaignID`),
  KEY `LayoutID` (`LayoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lkdisplaydg`
--

CREATE TABLE IF NOT EXISTS `lkdisplaydg` (
  `LkDisplayDGID` int(11) NOT NULL AUTO_INCREMENT,
  `DisplayGroupID` int(11) NOT NULL,
  `DisplayID` int(11) NOT NULL,
  PRIMARY KEY (`LkDisplayDGID`),
  UNIQUE KEY `DisplayGroupDisplayId` (`DisplayGroupID`,`DisplayID`),
  KEY `DisplayGroupID` (`DisplayGroupID`),
  KEY `DisplayID` (`DisplayID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lkmediadisplaygroup`
--

CREATE TABLE IF NOT EXISTS `lkmediadisplaygroup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mediaid` int(11) NOT NULL,
  `displaygroupid` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mediaid` (`mediaid`,`displaygroupid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='File associations directly to Display Groups' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lklayoutdisplaygroup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `layoutId` int(11) NOT NULL,
  `displayGroupId` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `layoutId` (`layoutId`,`displaygroupid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Layout associations directly to Display Groups' AUTO_INCREMENT=1 ;


--
-- Table structure for table `lkregionplaylist`
--

CREATE TABLE IF NOT EXISTS `lkregionplaylist` (
  `regionId` int(11) NOT NULL,
  `playlistId` int(11) NOT NULL,
  `displayOrder` int(11) NOT NULL,
  PRIMARY KEY (`regionId`,`playlistId`,`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `lkscheduledisplaygroup`
--

CREATE TABLE IF NOT EXISTS `lkscheduledisplaygroup` (
  `eventId` int(11) NOT NULL,
  `displayGroupId` int(11) NOT NULL,
  PRIMARY KEY (`eventId`,`displayGroupId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `lktaglayout`
--

CREATE TABLE IF NOT EXISTS `lktaglayout` (
  `lkTagLayoutId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `layoutId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagLayoutId`),
  UNIQUE KEY `tagId` (`tagId`,`layoutId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lktagmedia`
--

CREATE TABLE IF NOT EXISTS `lktagmedia` (
  `lkTagMediaId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `mediaId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagMediaId`),
  UNIQUE KEY `tagId` (`tagId`,`mediaId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lktagcampaign`
--

CREATE TABLE IF NOT EXISTS `lktagcampaign` (
  `lkTagCampaignId` int(11) NOT NULL AUTO_INCREMENT,
  `tagId` int(11) NOT NULL,
  `campaignId` int(11) NOT NULL,
  PRIMARY KEY (`lkTagCampaignId`),
  UNIQUE KEY `tagId` (`tagId`,`campaignId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

create table lktagdisplaygroup
(
  lkTagDisplayGroupId int auto_increment primary key,
  tagId int not null,
  displayGroupId int not null,
  constraint tagId
  unique (tagId, displayGroupId)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Table structure for table `lkusergroup`
--

CREATE TABLE IF NOT EXISTS `lkusergroup` (
  `LkUserGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `GroupID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  PRIMARY KEY (`LkUserGroupID`),
  UNIQUE KEY `GroupID_2` (`GroupID`,`UserID`),
  KEY `GroupID` (`GroupID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lkwidgetmedia`
--

CREATE TABLE IF NOT EXISTS `lkwidgetmedia` (
  `widgetId` int(11) NOT NULL,
  `mediaId` int(11) NOT NULL,
  PRIMARY KEY (`widgetId`,`mediaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

CREATE TABLE IF NOT EXISTS `log` (
  `logid` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'The log ID',
  `runNo` varchar(10) NOT NULL,
  `logdate` datetime NOT NULL COMMENT 'The log date',
  `channel` varchar(20) NOT NULL,
  `type` varchar(254) NOT NULL,
  `page` varchar(50) NOT NULL,
  `function` varchar(50) DEFAULT NULL,
  `message` longtext NOT NULL,
  `userID` int(11) NOT NULL DEFAULT '0',
  `displayID` int(11) DEFAULT NULL,
  PRIMARY KEY (`logid`),
  KEY `logdate` (`logdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE IF NOT EXISTS `media` (
  `mediaID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(15) NOT NULL,
  `duration` int(11) NOT NULL,
  `originalFilename` varchar(254) DEFAULT NULL,
  `storedAs` varchar(254) DEFAULT NULL COMMENT 'What has this media been stored as',
  `MD5` varchar(32) DEFAULT NULL,
  `FileSize` bigint(20) DEFAULT NULL,
  `userID` int(11) NOT NULL,
  `retired` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Is retired?',
  `isEdited` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Is this the current record',
  `editedMediaID` int(11) DEFAULT NULL COMMENT 'The Parent ID',
  `moduleSystemFile` tinyint(1) NOT NULL DEFAULT '0',
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  `expires` int(11) DEFAULT NULL,
  `released` tinyint(4) NOT NULL DEFAULT '1',
  `apiRef` varchar(254) NULL,
  `createdDt` DATETIME NULL,
  `modifiedDt` DATETIME NULL,
  PRIMARY KEY (`mediaID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `module`
--

CREATE TABLE IF NOT EXISTS `module` (
  `ModuleID` int(11) NOT NULL AUTO_INCREMENT,
  `Module` varchar(50) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Enabled` tinyint(4) NOT NULL DEFAULT '0',
  `RegionSpecific` tinyint(4) NOT NULL DEFAULT '1',
  `Description` varchar(254) DEFAULT NULL,
  `ImageUri` varchar(254) NOT NULL,
  `SchemaVersion` int(11) NOT NULL DEFAULT '1',
  `ValidExtensions` varchar(254) DEFAULT NULL,
  `PreviewEnabled` tinyint(4) NOT NULL DEFAULT '1',
  `assignable` tinyint(4) NOT NULL DEFAULT '1',
  `render_as` varchar(10) DEFAULT NULL,
  `settings` text,
  `viewPath` varchar(254) NOT NULL DEFAULT '../modules',
  `class` varchar(254) NOT NULL,
  `defaultDuration` int(11) NOT NULL,
  `installName` varchar(254) NULL,
  PRIMARY KEY (`ModuleID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Functional Modules' AUTO_INCREMENT=32 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_access_tokens`
--

CREATE TABLE IF NOT EXISTS `oauth_access_tokens` (
  `access_token` varchar(254) NOT NULL,
  `session_id` int(10) unsigned NOT NULL,
  `expire_time` int(11) NOT NULL,
  PRIMARY KEY (`access_token`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

CREATE TABLE IF NOT EXISTS `oauth_clients` (
  `id` varchar(254) NOT NULL,
  `secret` varchar(254) NOT NULL,
  `name` varchar(254) NOT NULL,
  `userId` int(11) NOT NULL,
  `authCode` tinyint(4) NOT NULL,
  `clientCredentials` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_client_redirect_uris`
--

CREATE TABLE IF NOT EXISTS `oauth_client_redirect_uris` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(254) NOT NULL,
  `redirect_uri` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


CREATE TABLE oauth_client_scopes(
  clientId varchar(254) NOT NULL,
  scopeId varchar(254) NOT NULL,
  id int PRIMARY KEY AUTO_INCREMENT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_scopes`
--

CREATE TABLE IF NOT EXISTS `oauth_scopes` (
  `id` varchar(254) NOT NULL,
  `description` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oauth_scope_routes`
(
  scopeId varchar(254) NOT NULL,
  route varchar(1000) NOT NULL,
  method varchar(8) NOT NULL,
  id int PRIMARY KEY AUTO_INCREMENT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
  `pageID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `asHome` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pageID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Available Pages' AUTO_INCREMENT=36 ;

-- --------------------------------------------------------

--
-- Table structure for table `permission`
--

CREATE TABLE IF NOT EXISTS `permission` (
  `permissionId` int(11) NOT NULL AUTO_INCREMENT,
  `entityId` int(11) NOT NULL,
  `groupId` int(11) NOT NULL,
  `objectId` int(11) NOT NULL,
  `view` tinyint(4) NOT NULL,
  `edit` tinyint(4) NOT NULL,
  `delete` tinyint(4) NOT NULL,
  PRIMARY KEY (`permissionId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `permissionentity`
--

CREATE TABLE IF NOT EXISTS `permissionentity` (
  `entityId` int(11) NOT NULL AUTO_INCREMENT,
  `entity` varchar(50) NOT NULL,
  PRIMARY KEY (`entityId`),
  UNIQUE KEY `entity` (`entity`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `playlist`
--

CREATE TABLE IF NOT EXISTS `playlist` (
  `playlistId` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(254) DEFAULT NULL,
  `ownerId` int(11) NOT NULL,
  PRIMARY KEY (`playlistId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `region`
--

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
  `duration` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`regionId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `regionoption`
--

CREATE TABLE IF NOT EXISTS `regionoption` (
  `regionId` int(11) NOT NULL,
  `option` varchar(50) NOT NULL,
  `value` text NULL,
  PRIMARY KEY (`regionId`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `resolution`
--

CREATE TABLE IF NOT EXISTS `resolution` (
  `resolutionID` int(11) NOT NULL AUTO_INCREMENT,
  `resolution` varchar(254) NOT NULL,
  `width` smallint(6) NOT NULL,
  `height` smallint(6) NOT NULL,
  `intended_width` smallint(6) NOT NULL,
  `intended_height` smallint(6) NOT NULL,
  `version` tinyint(4) NOT NULL DEFAULT '1',
  `enabled` tinyint(4) NOT NULL DEFAULT '1',
  `userId` tinyint(4) NOT NULL,
  PRIMARY KEY (`resolutionID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Supported Resolutions' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE IF NOT EXISTS `schedule` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `eventTypeId` tinyint(4) NOT NULL,
  `CampaignID` int(11) DEFAULT NULL,
  `commandId` int(11) DEFAULT NULL,
  `recurrence_type` enum('Minute','Hour','Day','Week','Month','Year') DEFAULT NULL,
  `recurrence_detail` varchar(100) DEFAULT NULL,
  `userID` int(11) NOT NULL,
  `is_priority` tinyint(4) NOT NULL,
  `FromDT` bigint(20) DEFAULT NULL,
  `ToDT` bigint(20) DEFAULT NULL,
  `recurrence_range` bigint(20) DEFAULT NULL,
  `DisplayOrder` int(11) NOT NULL DEFAULT '0',
  `dayPartId` int(11) NOT NULL DEFAULT '0',
  `recurrenceRepeatsOn` VARCHAR(14) NULL,
  `lastRecurrenceWatermark` BIGINT(20) NULL,
  `syncTimezone` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eventID`),
  KEY `layoutID` (`CampaignID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='High level schedule information' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE IF NOT EXISTS `session` (
  `session_id` varchar(160) NOT NULL,
  `session_data` longtext NOT NULL,
  `session_expiration` int(10) unsigned NOT NULL DEFAULT '0',
  `LastAccessed` datetime DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `IsExpired` tinyint(4) NOT NULL DEFAULT '1',
  `UserAgent` varchar(254) DEFAULT NULL,
  `RemoteAddr` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `userID` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE IF NOT EXISTS `setting` (
  `settingid` int(11) NOT NULL AUTO_INCREMENT,
  `setting` varchar(50) NOT NULL,
  `value` varchar(1000) NOT NULL,
  `fieldType` varchar(24) NOT NULL,
  `helptext` text,
  `options` varchar(254) DEFAULT NULL,
  `cat` varchar(24) NOT NULL DEFAULT 'general',
  `userChange` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Can the user change this setting',
  `title` varchar(254) NOT NULL,
  `validation` varchar(50) NOT NULL,
  `ordering` int(11) NOT NULL,
  `default` varchar(1000) NOT NULL,
  `userSee` tinyint(4) NOT NULL DEFAULT '1',
  `type` varchar(50) NOT NULL,
  PRIMARY KEY (`settingid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=85 ;

-- --------------------------------------------------------

--
-- Table structure for table `stat`
--

CREATE TABLE IF NOT EXISTS `stat` (
  `statID` bigint(20) NOT NULL AUTO_INCREMENT,
  `Type` varchar(20) NOT NULL,
  `statDate` datetime NOT NULL COMMENT 'State entry date',
  `scheduleID` int(8) NOT NULL,
  `displayID` int(4) NOT NULL,
  `layoutID` int(8) DEFAULT NULL,
  `mediaID` varchar(50) DEFAULT NULL,
  `start` datetime NOT NULL,
  `end` datetime DEFAULT NULL,
  `Tag` varchar(254) DEFAULT NULL,
  `widgetId` int(8) DEFAULT NULL,
  PRIMARY KEY (`statID`),
  KEY `statDate` (`statDate`),
  KEY `Type` (`displayID`,`end`,`Type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `tag`
--

CREATE TABLE IF NOT EXISTS `tag` (
  `tagId` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) NOT NULL,
  PRIMARY KEY (`tagId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `transition`
--

CREATE TABLE IF NOT EXISTS `transition` (
  `TransitionID` int(11) NOT NULL AUTO_INCREMENT,
  `Transition` varchar(254) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `HasDuration` tinyint(4) NOT NULL,
  `HasDirection` tinyint(4) NOT NULL,
  `AvailableAsIn` tinyint(4) NOT NULL,
  `AvailableAsOut` tinyint(4) NOT NULL,
  PRIMARY KEY (`TransitionID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `UserID` int(11) NOT NULL AUTO_INCREMENT,
  `usertypeid` int(8) NOT NULL,
  `UserName` varchar(50) NOT NULL,
  `UserPassword` varchar(255) NOT NULL,
  `lastaccessed` datetime DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL COMMENT 'The users email address',
  `homePageId` int(11) NOT NULL DEFAULT '1' COMMENT 'The users homepage',
  `Retired` tinyint(4) NOT NULL DEFAULT '0',
  `CSPRNG` tinyint(4) NOT NULL DEFAULT '0',
  `newUserWizard` tinyint(4) NOT NULL DEFAULT '0',
  `firstName` varchar(254) DEFAULT NULL,
  `lastName` varchar(254) DEFAULT NULL,
  `phone` varchar(254) DEFAULT NULL,
  `ref1` varchar(254) DEFAULT NULL,
  `ref2` varchar(254) DEFAULT NULL,
  `ref3` varchar(254) DEFAULT NULL,
  `ref4` varchar(254) DEFAULT NULL,
  `ref5` varchar(254) DEFAULT NULL,
  PRIMARY KEY (`UserID`),
  KEY `usertypeid` (`usertypeid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `usertype`
--

CREATE TABLE IF NOT EXISTS `usertype` (
  `usertypeid` int(8) NOT NULL,
  `usertype` varchar(16) NOT NULL,
  PRIMARY KEY (`usertypeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `version`
--

CREATE TABLE IF NOT EXISTS `version` (
  `app_ver` varchar(20) DEFAULT NULL,
  `XmdsVersion` smallint(6) NOT NULL,
  `XlfVersion` smallint(6) NOT NULL,
  `DBVersion` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version information';

-- --------------------------------------------------------

--
-- Table structure for table `widget`
--

CREATE TABLE IF NOT EXISTS `widget` (
  `widgetId` int(11) NOT NULL AUTO_INCREMENT,
  `playlistId` int(11) NOT NULL,
  `ownerId` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `duration` int(11) NOT NULL,
  `displayOrder` int(11) NOT NULL,
  `useDuration` int(4) NOT NULL DEFAULT '1',
  `calculatedDuration` int(4) NOT NULL,
  `createdDt` int(11) NOT NULL,
  `modifiedDt` int(11) NOT NULL,
  PRIMARY KEY (`widgetId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `widgetoption`
--

CREATE TABLE IF NOT EXISTS `widgetoption` (
  `widgetId` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `option` varchar(254) NOT NULL,
  `value` text NULL,
  PRIMARY KEY (`widgetId`,`type`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `command` (
  `commandId` int(11) NOT NULL AUTO_INCREMENT,
  `command` varchar(254) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`commandId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lkcommanddisplayprofile` (
  `commandId` int(11) NOT NULL,
  `displayProfileId` int(11) NOT NULL,
  `commandString` varchar(1000) NOT NULL,
  `validationString` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`commandId`,`displayProfileId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `useroption` (
  `userId` int(11) NOT NULL,
  `option` varchar(50) NOT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `userId` (`userId`,`option`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lkdgdg` (
  `parentId` int(11) NOT NULL,
  `childId` int(11) NOT NULL,
  `depth` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `lknotificationdg`
--

CREATE TABLE IF NOT EXISTS `lknotificationdg` (
  `lkNotificationDgId` int(11) NOT NULL AUTO_INCREMENT,
  `notificationId` int(11) NOT NULL,
  `displayGroupId` int(11) NOT NULL,
  PRIMARY KEY (`lkNotificationDgId`),
  UNIQUE KEY `notificationId` (`notificationId`,`displayGroupId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lknotificationgroup`
--

CREATE TABLE IF NOT EXISTS `lknotificationgroup` (
  `lkNotificationGroupId` int(11) NOT NULL AUTO_INCREMENT,
  `notificationId` int(11) NOT NULL,
  `groupId` int(11) NOT NULL,
  PRIMARY KEY (`lkNotificationGroupId`),
  UNIQUE KEY `notificationId` (`notificationId`,`groupId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lknotificationuser`
--

CREATE TABLE IF NOT EXISTS `lknotificationuser` (
  `lkNotificationUserId` int(11) NOT NULL AUTO_INCREMENT,
  `notificationId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `read` tinyint(4) NOT NULL,
  `readDt` int(11) NOT NULL,
  `emailDt` int(11) NOT NULL,
  PRIMARY KEY (`lkNotificationUserId`),
  UNIQUE KEY `notificationId` (`notificationId`,`userId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE IF NOT EXISTS `notification` (
  `notificationId` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `createDt` int(11) NOT NULL,
  `releaseDt` int(11) NOT NULL,
  `isEmail` tinyint(4) NOT NULL,
  `isInterrupt` tinyint(4) NOT NULL,
  `isSystem` tinyint(4) NOT NULL,
  `userId` int(11) NOT NULL,
  PRIMARY KEY (`notificationId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lkwidgetaudio` (
  widgetId int NOT NULL,
  mediaId int NOT NULL,
  volume tinyint DEFAULT 100,
  `loop` tinyint DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `displayevent` (
  `displayEventId` bigint(20) NOT NULL AUTO_INCREMENT,
  `eventDate` int(11) NOT NULL,
  `displayId` int(4) NOT NULL,
  `start` int(11) NOT NULL,
  `end` int(11) DEFAULT NULL,
  PRIMARY KEY (`displayEventId`),
  KEY `eventDate` (`eventDate`),
  KEY `displayId` (`displayID`,`end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- Auto increment 2 on purpose - 1 is reserved
CREATE TABLE `daypart` (
  `dayPartId` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(1000),
  `isRetired` TINYINT(4) DEFAULT 0,
  `userid` INT(11) NOT NULL,
  `startTime` VARCHAR(8) DEFAULT '00:00:00',
  `endTime` VARCHAR(8) DEFAULT '00:00:00',
  `exceptions` TEXT NULL,
  `isAlways` TINYINT(4) DEFAULT 0 NOT NULL,
  `isCustom` TINYINT(4) DEFAULT 0 NOT NULL,
  PRIMARY KEY (`dayPartId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE `task` (
  `taskId` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(254) NOT NULL,
  `class` VARCHAR(254) NOT NULL,
  `status` TINYINT(4) DEFAULT '2' NOT NULL,
  `pid` INT(11),
  `options` TEXT,
  `schedule` VARCHAR(254),
  `lastRunDt` INT(11),
  `lastRunStartDt` INT(11),
  `lastRunMessage` VARCHAR(254),
  `lastRunStatus` TINYINT(4) DEFAULT '0' NOT NULL,
  `lastRunDuration` SMALLINT(6),
  `lastRunExitCode` SMALLINT(6),
  `isActive` TINYINT(4) DEFAULT '1' NOT NULL,
  `runNow` TINYINT(4) DEFAULT '1' NOT NULL,
  `configFile` VARCHAR(254) NOT NULL,
  PRIMARY KEY (`taskId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=6;

CREATE TABLE IF NOT EXISTS `requiredfile` (
  `rfId` bigint(20) NOT NULL AUTO_INCREMENT,
  `displayId` int(11) NOT NULL,
  `type` varchar(1) NOT NULL,
  `itemId` int(11) DEFAULT NULL,
  `bytesRequested` bigint(20) NOT NULL,
  `complete` tinyint(1) DEFAULT 0 NOT NULL,
  `path` varchar(255) NULL,
  `size` BIGINT(20) DEFAULT 0 NOT NULL,
  PRIMARY KEY (`rfId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;