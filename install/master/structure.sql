
CREATE TABLE IF NOT EXISTS `bandwidth` (
  `DisplayID` int(11) NOT NULL,
  `Type` tinyint(4) NOT NULL,
  `Month` int(11) NOT NULL,
  `Size` bigint(20) NOT NULL,
  PRIMARY KEY (`DisplayID`, `Type`, `Month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE IF NOT EXISTS `campaign` (
  `CampaignID` int(11) NOT NULL AUTO_INCREMENT,
  `Campaign` varchar(254) NOT NULL,
  `IsLayoutSpecific` tinyint(4) NOT NULL,
  `UserID` int(11) NOT NULL,
  PRIMARY KEY (`CampaignID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

CREATE TABLE IF NOT EXISTS `dataset` (
  `DataSetID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSet` varchar(50) NOT NULL,
  `Description` varchar(254) DEFAULT NULL,
  `UserID` int(11) NOT NULL,
  `LastDataEdit` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`DataSetID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `datasetcolumn` (
  `DataSetColumnID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSetID` int(11) NOT NULL,
  `Heading` varchar(50) NOT NULL,
  `DataTypeID` smallint(6) NOT NULL,
  `DataSetColumnTypeID` smallint(6) NOT NULL,
  `ListContent` varchar(255) DEFAULT NULL,
  `ColumnOrder` smallint(6) NOT NULL,
  `Formula` VARCHAR( 1000 ) NULL,
  PRIMARY KEY (`DataSetColumnID`),
  KEY `DataSetID` (`DataSetID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `datasetdata` (
  `DataSetDataID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSetColumnID` int(11) NOT NULL,
  `RowNumber` int(11) NOT NULL,
  `Value` varchar(255) NOT NULL,
  PRIMARY KEY (`DataSetDataID`),
  KEY `DataColumnID` (`DataSetColumnID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `display` (
  `displayid` int(11) NOT NULL AUTO_INCREMENT,
  `isAuditing` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Is this display auditing',
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
  `MediaInventoryXml` longtext,
  `MacAddress` varchar(254) DEFAULT NULL COMMENT 'Mac Address of the Client',
  `LastChanged` int(11) DEFAULT NULL COMMENT 'Last time this Mac Address changed',
  `NumberOfMacAddressChanges` int(11) NOT NULL DEFAULT '0',
  `LastWakeOnLanCommandSent` int(11) DEFAULT NULL,
  `WakeOnLan` tinyint(4) NOT NULL DEFAULT '0',
  `WakeOnLanTime` varchar(5) DEFAULT NULL,
  `BroadCastAddress` varchar(100) DEFAULT NULL,
  `SecureOn` varchar(17) DEFAULT NULL,
  `Cidr` varchar(6) DEFAULT NULL,
  `GeoLocation` POINT NULL,
  `version_instructions` varchar(255) NULL,
  `client_type` VARCHAR( 20 ) NULL ,
  `client_version` VARCHAR( 15 ) NULL ,
  `client_code` SMALLINT NULL,
  `displayprofileid` int(11) NULL,
  `currentLayoutId` int(11) NULL,
  `screenShotRequested` tinyint(4) NOT NULL DEFAULT '0',
  `storageAvailableSpace` bigint(20) NULL,
  `storageTotalSpace` bigint(20) NULL,
  PRIMARY KEY (`displayid`),
  KEY `defaultplaylistid` (`defaultlayoutid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `displaygroup` (
  `DisplayGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `DisplayGroup` varchar(50) NOT NULL,
  `Description` varchar(254) DEFAULT NULL,
  `IsDisplaySpecific` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`DisplayGroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

CREATE TABLE IF NOT EXISTS `file` (
  `FileID` int(11) NOT NULL AUTO_INCREMENT,
  `CreatedDT` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  PRIMARY KEY (`FileID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `group` (
  `groupID` int(11) NOT NULL AUTO_INCREMENT,
  `group` varchar(50) NOT NULL,
  `IsUserSpecific` tinyint(4) NOT NULL DEFAULT '0',
  `IsEveryone` tinyint(4) NOT NULL DEFAULT '0',
  `libraryQuota` int(11) NULL,
  PRIMARY KEY (`groupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Groups' AUTO_INCREMENT=4 ;

CREATE TABLE IF NOT EXISTS `help` (
  `HelpID` int(11) NOT NULL AUTO_INCREMENT,
  `Topic` varchar(254) NOT NULL,
  `Category` varchar(254) NOT NULL DEFAULT 'General',
  `Link` varchar(254) NOT NULL,
  PRIMARY KEY (`HelpID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=78 ;

CREATE TABLE IF NOT EXISTS `layout` (
  `layoutID` int(11) NOT NULL AUTO_INCREMENT,
  `layout` varchar(50) NOT NULL,
  `xml` longtext NOT NULL,
  `userID` int(11) NOT NULL COMMENT 'The UserID that created this layout',
  `createdDT` datetime NOT NULL,
  `modifiedDT` datetime NOT NULL,
  `description` varchar(254) DEFAULT NULL,
  `tags` varchar(254) DEFAULT NULL,
  `retired` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Is this layout retired',
  `duration` int(11) NOT NULL DEFAULT '0' COMMENT 'The duration in seconds',
  `backgroundImageId` int(11) DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT  '0',
  PRIMARY KEY (`layoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Layouts' AUTO_INCREMENT=5 ;

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

CREATE TABLE IF NOT EXISTS `lkcampaignlayout` (
  `LkCampaignLayoutID` int(11) NOT NULL AUTO_INCREMENT,
  `CampaignID` int(11) NOT NULL,
  `LayoutID` int(11) NOT NULL,
  `DisplayOrder` int(11) NOT NULL,
  PRIMARY KEY (`LkCampaignLayoutID`),
  KEY `CampaignID` (`CampaignID`),
  KEY `LayoutID` (`LayoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

CREATE TABLE IF NOT EXISTS `lkdatasetgroup` (
  `LkDataSetGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSetID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkDataSetGroupID`),
  KEY `DataSetID` (`DataSetID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lkdisplaydg` (
  `LkDisplayDGID` int(11) NOT NULL AUTO_INCREMENT,
  `DisplayGroupID` int(11) NOT NULL,
  `DisplayID` int(11) NOT NULL,
  PRIMARY KEY (`LkDisplayDGID`),
  KEY `DisplayGroupID` (`DisplayGroupID`),
  KEY `DisplayID` (`DisplayID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

CREATE TABLE IF NOT EXISTS `lkdisplaygroupgroup` (
  `LkDisplayGroupGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `GroupID` int(11) NOT NULL,
  `DisplayGroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkDisplayGroupGroupID`),
  KEY `GroupID` (`GroupID`),
  KEY `DisplayGroupID` (`DisplayGroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lklayoutmedia` (
  `lklayoutmediaID` int(11) NOT NULL AUTO_INCREMENT COMMENT 'The ID',
  `mediaID` int(11) NOT NULL,
  `layoutID` int(11) NOT NULL,
  `regionID` varchar(50) NOT NULL COMMENT 'Region ID in the XML',
  PRIMARY KEY (`lklayoutmediaID`),
  KEY `mediaID` (`mediaID`),
  KEY `layoutID` (`layoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Creates a reference between Layout and Media' AUTO_INCREMENT=7 ;

CREATE TABLE IF NOT EXISTS `lklayoutmediagroup` (
  `LkLayoutMediaGroup` int(11) NOT NULL AUTO_INCREMENT,
  `LayoutID` int(11) NOT NULL,
  `RegionID` varchar(50) NOT NULL,
  `MediaID` varchar(50) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkLayoutMediaGroup`),
  KEY `LayoutID` (`LayoutID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lklayoutregiongroup` (
  `LkLayoutRegionGroup` int(11) NOT NULL AUTO_INCREMENT,
  `LayoutID` int(11) NOT NULL,
  `RegionID` varchar(50) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkLayoutRegionGroup`),
  KEY `LayoutID` (`LayoutID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lkmediagroup` (
  `LkMediaGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `MediaID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkMediaGroupID`),
  KEY `MediaID` (`MediaID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lkmenuitemgroup` (
  `LkMenuItemGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `GroupID` int(11) NOT NULL,
  `MenuItemID` int(11) NOT NULL,
  PRIMARY KEY (`LkMenuItemGroupID`),
  KEY `GroupID` (`GroupID`),
  KEY `MenuItemID` (`MenuItemID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=13 ;

CREATE TABLE IF NOT EXISTS `lkpagegroup` (
  `lkpagegroupID` int(11) NOT NULL AUTO_INCREMENT,
  `pageID` int(11) NOT NULL,
  `groupID` int(11) NOT NULL,
  PRIMARY KEY (`lkpagegroupID`),
  KEY `pageID` (`pageID`),
  KEY `groupID` (`groupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Pages available to groups' AUTO_INCREMENT=55 ;

CREATE TABLE IF NOT EXISTS `lkusergroup` (
  `LkUserGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `GroupID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  PRIMARY KEY (`LkUserGroupID`),
  KEY `GroupID` (`GroupID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=11 ;

CREATE TABLE IF NOT EXISTS `log` (
  `logid` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'The log ID',
  `logdate` datetime NOT NULL COMMENT 'The log date',
  `type` enum('error','audit') NOT NULL,
  `page` varchar(50) NOT NULL,
  `function` varchar(50) DEFAULT NULL,
  `message` longtext NOT NULL,
  `RequestUri` varchar(2000) DEFAULT NULL,
  `RemoteAddr` varchar(254) DEFAULT NULL,
  `userID` int(11) NOT NULL DEFAULT '0',
  `UserAgent` varchar(254) DEFAULT NULL,
  `scheduleID` int(11) DEFAULT NULL,
  `displayID` int(11) DEFAULT NULL,
  `layoutID` int(11) DEFAULT NULL,
  `mediaID` int(11) DEFAULT NULL,
  PRIMARY KEY (`logid`),
  KEY `logdate` (`logdate`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=372 ;

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
  `expires` int(11) NULL,
  PRIMARY KEY (`mediaID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

CREATE TABLE IF NOT EXISTS `menu` (
  `MenuID` smallint(6) NOT NULL AUTO_INCREMENT,
  `Menu` varchar(50) NOT NULL,
  PRIMARY KEY (`MenuID`),
  UNIQUE KEY `Menu` (`Menu`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='A Menu (or collection of links)' AUTO_INCREMENT=10 ;

CREATE TABLE IF NOT EXISTS `menuitem` (
  `MenuItemID` int(11) NOT NULL AUTO_INCREMENT,
  `MenuID` smallint(6) NOT NULL,
  `PageID` int(11) NOT NULL,
  `Args` varchar(254) DEFAULT NULL,
  `Text` varchar(20) NOT NULL,
  `Class` varchar(50) DEFAULT NULL,
  `Img` varchar(254) DEFAULT NULL,
  `Sequence` smallint(6) NOT NULL DEFAULT '1',
  `External` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`MenuItemID`),
  KEY `PageID` (`PageID`),
  KEY `MenuID` (`MenuID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=40 ;

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
  `settings` TEXT,
  PRIMARY KEY (`ModuleID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Functional Modules' AUTO_INCREMENT=14 ;

CREATE TABLE IF NOT EXISTS `oauth_log` (
  `olg_id` int(11) NOT NULL AUTO_INCREMENT,
  `olg_osr_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `olg_ost_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `olg_ocr_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `olg_oct_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `olg_usa_id_ref` int(11) DEFAULT NULL,
  `olg_received` text NOT NULL,
  `olg_sent` text NOT NULL,
  `olg_base_string` text NOT NULL,
  `olg_notes` text NOT NULL,
  `olg_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `olg_remote_ip` bigint(20) NOT NULL,
  PRIMARY KEY (`olg_id`),
  KEY `olg_osr_consumer_key` (`olg_osr_consumer_key`,`olg_id`),
  KEY `olg_ost_token` (`olg_ost_token`,`olg_id`),
  KEY `olg_ocr_consumer_key` (`olg_ocr_consumer_key`,`olg_id`),
  KEY `olg_oct_token` (`olg_oct_token`,`olg_id`),
  KEY `olg_usa_id_ref` (`olg_usa_id_ref`,`olg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `oauth_server_nonce` (
  `osn_id` int(11) NOT NULL AUTO_INCREMENT,
  `osn_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `osn_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `osn_timestamp` bigint(20) NOT NULL,
  `osn_nonce` varchar(80) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`osn_id`),
  UNIQUE KEY `osn_consumer_key` (`osn_consumer_key`,`osn_token`,`osn_timestamp`,`osn_nonce`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `oauth_server_registry` (
  `osr_id` int(11) NOT NULL AUTO_INCREMENT,
  `osr_usa_id_ref` int(11) DEFAULT NULL,
  `osr_consumer_key` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `osr_consumer_secret` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `osr_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `osr_status` varchar(16) NOT NULL,
  `osr_requester_name` varchar(64) NOT NULL,
  `osr_requester_email` varchar(64) NOT NULL,
  `osr_callback_uri` varchar(255) NOT NULL,
  `osr_application_uri` varchar(255) NOT NULL,
  `osr_application_title` varchar(80) NOT NULL,
  `osr_application_descr` text NOT NULL,
  `osr_application_notes` text NOT NULL,
  `osr_application_type` varchar(20) NOT NULL,
  `osr_application_commercial` tinyint(1) NOT NULL DEFAULT '0',
  `osr_issue_date` datetime NOT NULL,
  `osr_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`osr_id`),
  UNIQUE KEY `osr_consumer_key` (`osr_consumer_key`),
  KEY `osr_usa_id_ref` (`osr_usa_id_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `oauth_server_token` (
  `ost_id` int(11) NOT NULL AUTO_INCREMENT,
  `ost_osr_id_ref` int(11) NOT NULL,
  `ost_usa_id_ref` int(11) NOT NULL,
  `ost_token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ost_token_secret` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ost_token_type` enum('request','access') DEFAULT NULL,
  `ost_authorized` tinyint(1) NOT NULL DEFAULT '0',
  `ost_referrer_host` varchar(128) NOT NULL,
  `ost_token_ttl` datetime NOT NULL DEFAULT '9999-12-31 00:00:00',
  `ost_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ost_verifier` char(10) DEFAULT NULL,
  `ost_callback_url` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`ost_id`),
  UNIQUE KEY `ost_token` (`ost_token`),
  KEY `ost_osr_id_ref` (`ost_osr_id_ref`),
  KEY `ost_token_ttl` (`ost_token_ttl`),
  KEY `ost_usa_id_ref` (`ost_usa_id_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `pagegroup` (
  `pagegroupID` int(11) NOT NULL AUTO_INCREMENT,
  `pagegroup` varchar(50) NOT NULL,
  PRIMARY KEY (`pagegroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Page Groups' AUTO_INCREMENT=15 ;

CREATE TABLE IF NOT EXISTS `pages` (
  `pageID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `pagegroupID` int(11) NOT NULL,
  PRIMARY KEY (`pageID`),
  KEY `pagegroupID` (`pagegroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Available Pages' AUTO_INCREMENT=40 ;

CREATE TABLE IF NOT EXISTS `resolution` (
  `resolutionID` int(11) NOT NULL AUTO_INCREMENT,
  `resolution` varchar(254) NOT NULL,
  `width` smallint(6) NOT NULL,
  `height` smallint(6) NOT NULL,
  `intended_width` smallint(6) NOT NULL,
  `intended_height` smallint(6) NOT NULL,
  `version` tinyint(4) NOT NULL DEFAULT '1',
  `enabled` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`resolutionID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Supported Resolutions' AUTO_INCREMENT=9 ;

CREATE TABLE IF NOT EXISTS `schedule` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `CampaignID` int(11) NOT NULL,
  `DisplayGroupIDs` varchar(254) NOT NULL COMMENT 'A list of the display group ids for this event',
  `recurrence_type` enum('Minute','Hour','Day','Week','Month','Year') DEFAULT NULL,
  `recurrence_detail` varchar(100) DEFAULT NULL,
  `userID` int(11) NOT NULL,
  `is_priority` tinyint(4) NOT NULL,
  `FromDT` bigint(20) NOT NULL DEFAULT '0',
  `ToDT` bigint(20) NOT NULL DEFAULT '0',
  `recurrence_range` bigint(20) DEFAULT NULL,
  `DisplayOrder` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eventID`),
  KEY `layoutID` (`CampaignID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='High level schedule information' AUTO_INCREMENT=2 ;

CREATE TABLE IF NOT EXISTS `schedule_detail` (
  `schedule_detailID` int(11) NOT NULL AUTO_INCREMENT,
  `DisplayGroupID` int(11) NOT NULL,
  `userID` int(8) NOT NULL DEFAULT '1' COMMENT 'Owner of the Event',
  `eventID` int(11) DEFAULT NULL,
  `FromDT` bigint(20) NOT NULL DEFAULT '0',
  `ToDT` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`schedule_detailID`),
  KEY `scheduleID` (`eventID`),
  KEY `DisplayGroupID` (`DisplayGroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Replicated schedule across displays and recurrence' AUTO_INCREMENT=6 ;

CREATE TABLE IF NOT EXISTS `session` (
  `session_id` varchar(160) NOT NULL,
  `session_data` longtext NOT NULL,
  `session_expiration` int(10) unsigned NOT NULL DEFAULT '0',
  `LastAccessed` datetime DEFAULT NULL,
  `LastPage` varchar(25) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `IsExpired` tinyint(4) NOT NULL DEFAULT '1',
  `UserAgent` varchar(254) DEFAULT NULL,
  `RemoteAddr` varchar(50) DEFAULT NULL,
  `SecurityToken` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `userID` (`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=74 ;

CREATE TABLE IF NOT EXISTS `stat` (
  `statID` bigint(20) NOT NULL AUTO_INCREMENT,
  `Type` varchar(20) NOT NULL,
  `statDate` datetime NOT NULL COMMENT 'State entry date',
  `scheduleID` int(8) NOT NULL,
  `displayID` int(4) NOT NULL,
  `layoutID` int(8) NULL,
  `mediaID` varchar(50) DEFAULT NULL,
  `start` datetime NOT NULL,
  `end` datetime NULL,
  `Tag` varchar(254) DEFAULT NULL,
  PRIMARY KEY (`statID`),
  KEY `statDate` (`statDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `transition` (
  `TransitionID` int(11) NOT NULL AUTO_INCREMENT,
  `Transition` varchar(254) NOT NULL,
  `Code` varchar(50) NOT NULL,
  `HasDuration` tinyint(4) NOT NULL,
  `HasDirection` tinyint(4) NOT NULL,
  `AvailableAsIn` tinyint(4) NOT NULL,
  `AvailableAsOut` tinyint(4) NOT NULL,
  PRIMARY KEY (`TransitionID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

CREATE TABLE IF NOT EXISTS `user` (
  `UserID` int(11) NOT NULL AUTO_INCREMENT,
  `usertypeid` int(8) NOT NULL,
  `UserName` varchar(50) NOT NULL,
  `UserPassword` varchar(128) NOT NULL,
  `loggedin` tinyint(1) NOT NULL DEFAULT '0',
  `lastaccessed` datetime DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL COMMENT 'The users email address',
  `homepage` varchar(254) NOT NULL DEFAULT 'dashboard.php' COMMENT 'The users homepage',
  `Retired` tinyint(4) NOT NULL DEFAULT '0',
  `CSPRNG` tinyint(4) NOT NULL DEFAULT '0',
  `newUserWizard` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`),
  KEY `usertypeid` (`usertypeid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

CREATE TABLE IF NOT EXISTS `usertype` (
  `usertypeid` int(8) NOT NULL,
  `usertype` varchar(16) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`usertypeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `version` (
  `app_ver` varchar(20) DEFAULT NULL,
  `XmdsVersion` smallint(6) NOT NULL,
  `XlfVersion` smallint(6) NOT NULL,
  `DBVersion` int(11) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Version information';



CREATE TABLE IF NOT EXISTS `datatype` (
  `DataTypeID` smallint(6) NOT NULL,
  `DataType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `datasetcolumntype` (
  `DataSetColumnTypeID` smallint(6) NOT NULL,
  `DataSetColumnType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `lkmediadisplaygroup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mediaid` int(11) NOT NULL,
  `displaygroupid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='File associations directly to Display Groups' AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `lkdatasetlayout` (
  `LkDataSetLayoutID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSetID` int(11) NOT NULL,
  `LayoutID` int(11) NOT NULL,
  `RegionID` varchar(50) NOT NULL,
  `MediaID` varchar(50) NOT NULL,
  PRIMARY KEY (`LkDataSetLayoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `displayprofile` (
  `displayprofileid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` varchar(15) NOT NULL,
  `config` text NOT NULL,
  `isdefault` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  PRIMARY KEY (`displayprofileid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

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

CREATE TABLE IF NOT EXISTS `bandwidthtype` (
  `bandwidthtypeid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  PRIMARY KEY (`bandwidthtypeid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=12 ;

CREATE TABLE IF NOT EXISTS `tag` (
  `tagId` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) NOT NULL,
  PRIMARY KEY (`tagId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

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

CREATE TABLE IF NOT EXISTS `auditlog` (
  `logId` int(11) NOT NULL AUTO_INCREMENT,
  `logDate` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `message` varchar(254) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `entityId` int(11) NOT NULL,
  `objectAfter` text NOT NULL,
  PRIMARY KEY (`logId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

ALTER TABLE `blacklist`
  ADD CONSTRAINT `blacklist_ibfk_1` FOREIGN KEY (`MediaID`) REFERENCES `media` (`mediaID`),
  ADD CONSTRAINT `blacklist_ibfk_2` FOREIGN KEY (`DisplayID`) REFERENCES `display` (`displayid`);

ALTER TABLE `campaign`
  ADD CONSTRAINT `campaign_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

ALTER TABLE `datasetcolumn`
  ADD CONSTRAINT `datasetcolumn_ibfk_1` FOREIGN KEY (`DataSetID`) REFERENCES `dataset` (`DataSetID`);

ALTER TABLE `datasetdata`
  ADD CONSTRAINT `datasetdata_ibfk_1` FOREIGN KEY (`DataSetColumnID`) REFERENCES `datasetcolumn` (`DataSetColumnID`);

ALTER TABLE `file`
  ADD CONSTRAINT `file_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

ALTER TABLE `lkcampaigngroup`
  ADD CONSTRAINT `lkcampaigngroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkcampaigngroup_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

ALTER TABLE `lkcampaignlayout`
  ADD CONSTRAINT `lkcampaignlayout_ibfk_2` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`),
  ADD CONSTRAINT `lkcampaignlayout_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

ALTER TABLE `lkdatasetgroup`
  ADD CONSTRAINT `lkdatasetgroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkdatasetgroup_ibfk_1` FOREIGN KEY (`DataSetID`) REFERENCES `dataset` (`DataSetID`);

ALTER TABLE `lkdisplaydg`
  ADD CONSTRAINT `lkdisplaydg_ibfk_1` FOREIGN KEY (`DisplayGroupID`) REFERENCES `displaygroup` (`DisplayGroupID`),
  ADD CONSTRAINT `lkdisplaydg_ibfk_2` FOREIGN KEY (`DisplayID`) REFERENCES `display` (`displayid`);

ALTER TABLE `lkdisplaygroupgroup`
  ADD CONSTRAINT `lkdisplaygroupgroup_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkdisplaygroupgroup_ibfk_2` FOREIGN KEY (`DisplayGroupID`) REFERENCES `displaygroup` (`DisplayGroupID`);

ALTER TABLE `lklayoutmedia`
  ADD CONSTRAINT `lklayoutmedia_ibfk_1` FOREIGN KEY (`mediaID`) REFERENCES `media` (`mediaID`),
  ADD CONSTRAINT `lklayoutmedia_ibfk_2` FOREIGN KEY (`layoutID`) REFERENCES `layout` (`layoutID`);

ALTER TABLE `lklayoutmediagroup`
  ADD CONSTRAINT `lklayoutmediagroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lklayoutmediagroup_ibfk_1` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`);

ALTER TABLE `lklayoutregiongroup`
  ADD CONSTRAINT `lklayoutregiongroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lklayoutregiongroup_ibfk_1` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`);

ALTER TABLE `lkmediagroup`
  ADD CONSTRAINT `lkmediagroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkmediagroup_ibfk_1` FOREIGN KEY (`MediaID`) REFERENCES `media` (`mediaID`);

ALTER TABLE `lkmenuitemgroup`
  ADD CONSTRAINT `lkmenuitemgroup_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkmenuitemgroup_ibfk_2` FOREIGN KEY (`MenuItemID`) REFERENCES `menuitem` (`MenuItemID`);

ALTER TABLE `lkpagegroup`
  ADD CONSTRAINT `lkpagegroup_ibfk_1` FOREIGN KEY (`pageID`) REFERENCES `pages` (`pageID`),
  ADD CONSTRAINT `lkpagegroup_ibfk_2` FOREIGN KEY (`groupID`) REFERENCES `group` (`groupID`);

ALTER TABLE `lkusergroup`
  ADD CONSTRAINT `lkusergroup_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`),
  ADD CONSTRAINT `lkusergroup_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`);

ALTER TABLE `menuitem`
  ADD CONSTRAINT `menuitem_ibfk_1` FOREIGN KEY (`MenuID`) REFERENCES `menu` (`MenuID`),
  ADD CONSTRAINT `menuitem_ibfk_2` FOREIGN KEY (`PageID`) REFERENCES `pages` (`pageID`);

ALTER TABLE `oauth_server_registry`
  ADD CONSTRAINT `oauth_server_registry_ibfk_1` FOREIGN KEY (`osr_usa_id_ref`) REFERENCES `user` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `oauth_server_token`
  ADD CONSTRAINT `oauth_server_token_ibfk_2` FOREIGN KEY (`ost_usa_id_ref`) REFERENCES `user` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `oauth_server_token_ibfk_1` FOREIGN KEY (`ost_osr_id_ref`) REFERENCES `oauth_server_registry` (`osr_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `pages`
  ADD CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`pagegroupID`) REFERENCES `pagegroup` (`pagegroupID`);

ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaign` (`CampaignID`);

ALTER TABLE `schedule_detail`
  ADD CONSTRAINT `schedule_detail_ibfk_7` FOREIGN KEY (`eventID`) REFERENCES `schedule` (`eventID`),
  ADD CONSTRAINT `schedule_detail_ibfk_8` FOREIGN KEY (`DisplayGroupID`) REFERENCES `displaygroup` (`DisplayGroupID`);

ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_2` FOREIGN KEY (`usertypeid`) REFERENCES `usertype` (`usertypeid`);
