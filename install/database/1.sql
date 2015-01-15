-- phpMyAdmin SQL Dump
-- version 2.11.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Dec 10, 2008 at 10:57 PM
-- Server version: 5.0.45
-- PHP Version: 5.2.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `xsm`
--

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE IF NOT EXISTS `blacklist` (
  `BlackListID` int(11) NOT NULL auto_increment,
  `MediaID` int(11) NOT NULL,
  `DisplayID` int(11) NOT NULL,
  `UserID` int(11) default NULL COMMENT 'Null if it came from a display',
  `ReportingDisplayID` int(11) default NULL COMMENT 'The display that reported the blacklist',
  `Reason` text NOT NULL,
  `isIgnored` tinyint(4) NOT NULL default '0' COMMENT 'Ignore this blacklist',
  PRIMARY KEY  (`BlackListID`),
  KEY `MediaID` (`MediaID`),
  KEY `DisplayID` (`DisplayID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Blacklisted media will not get sent to the Display' AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `display`
--

CREATE TABLE IF NOT EXISTS `display` (
  `displayid` int(8) NOT NULL auto_increment,
  `isAuditing` tinyint(4) NOT NULL default '0' COMMENT 'Is this display auditing',
  `display` varchar(50) NOT NULL,
  `defaultlayoutid` int(8) NOT NULL,
  `license` varchar(32) character set latin1 default NULL,
  `licensed` tinyint(1) NOT NULL default '0' COMMENT 'Is the Requested License Key Allowed',
  `loggedin` tinyint(4) NOT NULL default '0',
  `lastaccessed` datetime default NULL,
  `inc_schedule` tinyint(1) NOT NULL default '0' COMMENT 'Will this default be used in the scheduling calcs',
  PRIMARY KEY  (`displayid`),
  KEY `defaultplaylistid` (`defaultlayoutid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `group`
--

CREATE TABLE IF NOT EXISTS `group` (
  `groupID` int(11) NOT NULL auto_increment,
  `group` varchar(50) NOT NULL,
  PRIMARY KEY  (`groupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Groups' AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `layout`
--

CREATE TABLE IF NOT EXISTS `layout` (
  `layoutID` int(11) NOT NULL auto_increment,
  `layout` varchar(50) NOT NULL,
  `permissionID` int(11) NOT NULL default '0',
  `xml` text NOT NULL,
  `userID` int(11) NOT NULL COMMENT 'The UserID that created this layout',
  `createdDT` datetime NOT NULL,
  `modifiedDT` datetime NOT NULL,
  `description` varchar(254) default NULL,
  `tags` varchar(254) default NULL,
  `templateID` int(11) default NULL COMMENT 'The ID of the template',
  `retired` tinyint(4) NOT NULL default '0' COMMENT 'Is this layout retired',
  `duration` int(11) NOT NULL default '0' COMMENT 'The duration in seconds',
  `background` varchar(254) default NULL,
  PRIMARY KEY  (`layoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Layouts' AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `lklayoutmedia`
--

CREATE TABLE IF NOT EXISTS `lklayoutmedia` (
  `lklayoutmediaID` int(11) NOT NULL auto_increment COMMENT 'The ID',
  `mediaID` int(11) NOT NULL,
  `layoutID` int(11) NOT NULL,
  `regionID` varchar(50) NOT NULL COMMENT 'Region ID in the XML',
  PRIMARY KEY  (`lklayoutmediaID`),
  KEY `mediaID` (`mediaID`),
  KEY `layoutID` (`layoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Creates a reference between Layout and Media' AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Table structure for table `lkpagegroup`
--

CREATE TABLE IF NOT EXISTS `lkpagegroup` (
  `lkpagegroupID` int(11) NOT NULL auto_increment,
  `pageID` int(11) NOT NULL,
  `groupID` int(11) NOT NULL,
  PRIMARY KEY  (`lkpagegroupID`),
  KEY `pageID` (`pageID`),
  KEY `groupID` (`groupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Pages available to groups' AUTO_INCREMENT=55 ;

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

CREATE TABLE IF NOT EXISTS `log` (
  `logid` bigint(20) NOT NULL auto_increment COMMENT 'The log ID',
  `logdate` datetime NOT NULL COMMENT 'The log date',
  `type` enum('error','audit') NOT NULL,
  `page` varchar(15) NOT NULL,
  `function` varchar(50) default NULL,
  `message` longtext NOT NULL,
  `RequestUri` varchar(254) default NULL,
  `RemoteAddr` varchar(254) default NULL,
  `userID` int(11) NOT NULL default '0',
  `UserAgent` varchar(254) default NULL,
  `scheduleID` int(11) default NULL,
  `displayID` int(11) default NULL,
  `layoutID` int(11) default NULL,
  `mediaID` int(11) default NULL,
  PRIMARY KEY  (`logid`),
  KEY `logdate` (`logdate`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=372 ;

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE IF NOT EXISTS `media` (
  `mediaID` int(11) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL,
  `type` varchar(10) NOT NULL,
  `duration` int(11) NOT NULL,
  `originalFilename` varchar(254) default NULL,
  `storedAs` varchar(254) default NULL COMMENT 'What has this media been stored as',
  `userID` int(11) NOT NULL,
  `permissionID` tinyint(1) NOT NULL default '1',
  `retired` tinyint(4) NOT NULL default '0' COMMENT 'Is retired?',
  `isEdited` tinyint(4) NOT NULL default '0' COMMENT 'Is this the current record',
  `editedMediaID` int(11) default NULL COMMENT 'The Parent ID',
  PRIMARY KEY  (`mediaID`),
  KEY `permissionID` (`permissionID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

-- --------------------------------------------------------

--
-- Table structure for table `module`
--

CREATE TABLE IF NOT EXISTS `module` (
  `ModuleID` int(11) NOT NULL auto_increment,
  `Module` varchar(50) NOT NULL,
  `Enabled` tinyint(4) NOT NULL default '0',
  `Description` varchar(254) default NULL,
  PRIMARY KEY  (`ModuleID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Functional Modules' AUTO_INCREMENT=8 ;

-- --------------------------------------------------------

--
-- Table structure for table `pagegroup`
--

CREATE TABLE IF NOT EXISTS `pagegroup` (
  `pagegroupID` int(11) NOT NULL auto_increment,
  `pagegroup` varchar(50) NOT NULL,
  PRIMARY KEY  (`pagegroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Page Groups' AUTO_INCREMENT=12 ;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
  `pageID` int(11) NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  `pagegroupID` int(11) NOT NULL,
  PRIMARY KEY  (`pageID`),
  KEY `pagegroupID` (`pagegroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Available Pages' AUTO_INCREMENT=26 ;

-- --------------------------------------------------------

--
-- Table structure for table `permission`
--

CREATE TABLE IF NOT EXISTS `permission` (
  `permissionID` tinyint(4) NOT NULL,
  `permission` varchar(50) NOT NULL,
  PRIMARY KEY  (`permissionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Permission Settings';

-- --------------------------------------------------------

--
-- Table structure for table `resolution`
--

CREATE TABLE IF NOT EXISTS `resolution` (
  `resolutionID` int(11) NOT NULL auto_increment,
  `resolution` varchar(20) NOT NULL,
  `width` smallint(6) NOT NULL,
  `height` smallint(6) NOT NULL,
  PRIMARY KEY  (`resolutionID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Supported Resolutions' AUTO_INCREMENT=5 ;

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE IF NOT EXISTS `schedule` (
  `eventID` int(11) NOT NULL auto_increment,
  `layoutID` int(11) NOT NULL,
  `displayID_list` varchar(50) NOT NULL,
  `recurrence_type` enum('Hour','Day','Week','Month','Year') default NULL,
  `recurrence_detail` varchar(100) default NULL,
  `recurrence_range` datetime default NULL,
  `start` datetime NOT NULL,
  `end` datetime NOT NULL,
  `userID` int(11) NOT NULL,
  `is_priority` tinyint(4) NOT NULL,
  PRIMARY KEY  (`eventID`),
  KEY `layoutID` (`layoutID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='High level schedule information' AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_detail`
--

CREATE TABLE IF NOT EXISTS `schedule_detail` (
  `schedule_detailID` int(11) NOT NULL auto_increment,
  `displayID` int(11) NOT NULL,
  `layoutID` int(11) NOT NULL,
  `starttime` datetime NOT NULL,
  `endtime` datetime NOT NULL,
  `userID` int(8) NOT NULL default '1' COMMENT 'Owner of the Event',
  `is_priority` tinyint(4) NOT NULL default '0' COMMENT 'This scheduled event has priority and will take precidence over any others scheduled',
  `eventID` int(11) default NULL,
  PRIMARY KEY  (`schedule_detailID`),
  KEY `displayid` (`displayID`),
  KEY `IM_SDT_DisplayID` (`starttime`,`displayID`),
  KEY `layoutID` (`layoutID`),
  KEY `scheduleID` (`eventID`),
  KEY `schedule_detail_ibfk_3` (`displayID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Replicated schedule across displays and recurrence' AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE IF NOT EXISTS `session` (
  `session_id` varchar(32) NOT NULL,
  `session_data` longtext NOT NULL,
  `session_expiration` int(10) unsigned NOT NULL default '0',
  `LastAccessed` datetime default NULL,
  `LastPage` varchar(25) default NULL,
  `userID` int(11) default NULL,
  `IsExpired` tinyint(4) NOT NULL default '1',
  `UserAgent` varchar(254) default NULL,
  `RemoteAddr` varchar(50) default NULL,
  `SecurityToken` varchar(50) default NULL,
  PRIMARY KEY  (`session_id`),
  KEY `userID` (`userID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

CREATE TABLE IF NOT EXISTS `setting` (
  `settingid` int(11) NOT NULL auto_increment,
  `setting` varchar(24) character set latin1 NOT NULL,
  `value` varchar(256) character set latin1 NOT NULL,
  `type` varchar(24) character set latin1 NOT NULL,
  `helptext` text character set latin1,
  `options` varchar(254) character set latin1 default NULL,
  `cat` varchar(24) character set latin1 NOT NULL default 'general',
  `userChange` tinyint(1) NOT NULL default '0' COMMENT 'Can the user change this setting',
  PRIMARY KEY  (`settingid`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=35 ;

-- --------------------------------------------------------

--
-- Table structure for table `stat`
--

CREATE TABLE IF NOT EXISTS `stat` (
  `statID` bigint(20) NOT NULL auto_increment,
  `statDate` datetime NOT NULL COMMENT 'State entry date',
  `scheduleID` int(8) NOT NULL,
  `displayID` int(4) NOT NULL,
  `layoutID` int(8) NOT NULL,
  `mediaID` varchar(50) NOT NULL,
  `start` datetime NOT NULL,
  `end` datetime NOT NULL,
  PRIMARY KEY  (`statID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `template`
--

CREATE TABLE IF NOT EXISTS `template` (
  `templateID` int(11) NOT NULL auto_increment,
  `template` varchar(50) NOT NULL,
  `xml` text NOT NULL,
  `permissionID` tinyint(6) NOT NULL,
  `userID` int(11) NOT NULL,
  `createdDT` datetime NOT NULL,
  `modifiedDT` datetime NOT NULL,
  `description` varchar(254) default NULL,
  `tags` varchar(254) default NULL,
  `thumbnail` varchar(100) default NULL,
  `isSystem` tinyint(4) NOT NULL default '0',
  `retired` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`templateID`),
  KEY `userID` (`userID`),
  KEY `permissionID` (`permissionID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Templates for use on Layouts' AUTO_INCREMENT=5 ;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `UserID` int(11) NOT NULL auto_increment,
  `usertypeid` int(8) NOT NULL,
  `UserName` varchar(15) character set latin1 NOT NULL,
  `UserPassword` varchar(32) character set latin1 NOT NULL,
  `loggedin` tinyint(1) NOT NULL default '0',
  `lastaccessed` datetime default NULL,
  `email` varchar(50) character set latin1 default NULL COMMENT 'The users email address',
  `groupID` int(11) NOT NULL,
  `homepage` varchar(254) NOT NULL default 'dashboard.php' COMMENT 'The users homepage',
  PRIMARY KEY  (`UserID`),
  KEY `usertypeid` (`usertypeid`),
  KEY `groupID` (`groupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `usertype`
--

CREATE TABLE IF NOT EXISTS `usertype` (
  `usertypeid` int(8) NOT NULL,
  `usertype` varchar(16) character set latin1 NOT NULL,
  PRIMARY KEY  (`usertypeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `version`
--

CREATE TABLE IF NOT EXISTS `version` (
  `app_ver` varchar(10) NOT NULL COMMENT 'The Application Version'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Version information';


--
-- Dumping data for table `group`
--

INSERT INTO `group` (`groupID`, `group`) VALUES
(1, 'Users');

--
-- Dumping data for table `lkpagegroup`
--

INSERT INTO `lkpagegroup` (`lkpagegroupID`, `pageID`, `groupID`) VALUES
(12, 2, 1),
(36, 1, 1),
(37, 3, 1),
(38, 19, 1),
(48, 5, 1),
(51, 7, 1),
(54, 24, 1);


--
-- Dumping data for table `pagegroup`
--

INSERT INTO `pagegroup` (`pagegroupID`, `pagegroup`) VALUES
(1, 'Schedule'),
(2, 'Homepage and Login'),
(3, 'Layouts'),
(4, 'Content'),
(7, 'Displays'),
(8, 'Users and Groups'),
(9, 'Reports'),
(10, 'License and Settings'),
(11, 'Updates');

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES
(1, 'dashboard', 2),
(2, 'schedule', 1),
(3, 'mediamanager', 2),
(5, 'layout', 3),
(7, 'content', 4),
(11, 'display', 7),
(12, 'update', 11),
(14, 'admin', 10),
(15, 'group', 8),
(16, 'report', 9),
(17, 'user', 8),
(18, 'license', 10),
(19, 'index', 2),
(24, 'module', 4),
(25, 'template', 3);

--
-- Dumping data for table `permission`
--

INSERT INTO `permission` (`permissionID`, `permission`) VALUES
(1, 'Private'),
(2, 'Group'),
(3, 'Public');

--
-- Dumping data for table `resolution`
--

INSERT INTO `resolution` (`resolutionID`, `resolution`, `width`, `height`) VALUES
(1, '4:3 Monitor', 800, 600),
(2, '3:2 Tv', 720, 480),
(3, '16:10 Widescreen Mon', 800, 500),
(4, '16:9 HD Widescreen', 800, 450);

--
-- Dumping data for table `setting`
--

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES
(1, 'defaultMedia', 'private', 'dropdown', 'Sets whether media is set to public or private by default.\r\n<br />\r\nWe recommend a setting of "Private"', 'private|public', 'default', 1),
(2, 'defaultPlaylist', 'private', 'dropdown', 'Sets whether playlists are set to public or private by default.\r\n<br />\r\nCurrently there is only a private option.', 'private|public', 'default', 1),
(3, 'defaultUsertype', 'user', 'dropdown', 'Sets the default user type selected when creating a user.\r\n<br />\r\nWe recommend that this is set to "User"', 'user|admin', 'default', 1),
(5, 'debug', 'Off', 'dropdown', 'Sets whether debug information is recorded when an error occurs.\r\n<br />\r\nThis should be set to "off" to ensure smaller log sizes', 'On|Off', 'error', 1),
(7, 'userModule', 'module_user_general.php', 'dirselect', 'This sets which user authentication module is currently being used.', NULL, 'user', 0),
(10, 'adminMessage', '', 'text', 'Sets the admin message to be displayed on the client page at all times', NULL, 'general', 0),
(11, 'defaultTimezone', 'Europe/London', 'dropdown', 'Set the default timezone for the application', 'Europe/London', 'default', 1),
(18, 'mail_to', 'support@xibo.org.uk', 'text', 'Errors will be mailed here', NULL, 'error', 0),
(19, 'mail_from', 'mail@yoursite.com', 'text', 'Mail will be sent from this address', NULL, 'error', 0),
(20, 'BASE_URL', 'http://localhost/xibo/', 'text', 'This is the fully qualified URI of the site. e.g http://www.xibo.co.uk/', NULL, 'general', 0),
(23, 'jpg_length', '10', 'text', 'Default length for JPG files (in seconds)', NULL, 'content', 1),
(24, 'ppt_width', '1024', 'text', 'Default length for PPT files', NULL, 'content', 0),
(25, 'ppt_height', '768', 'text', 'Default height for PPT files', NULL, 'content', 0),
(26, 'ppt_length', '120', 'text', 'Default length for PPT files (in seconds)', NULL, 'content', 1),
(29, 'swf_length', '60', 'text', 'Default length for SWF files', NULL, 'content', 1),
(30, 'audit', 'Off', 'dropdown', 'Turn on the auditing information. Warning this will quickly fill up the log', 'On|Off', 'error', 1),
(32, 'NUSOAP_PATH', '3rdparty/nuSoap/nusoap.php', 'text', NULL, NULL, 'path', 1),
(33, 'LIBRARY_LOCATION', '', 'text', NULL, NULL, 'path', 1),
(34, 'SERVER_KEY', 'xsm', 'text', NULL, NULL, 'general', 1);

--
-- Dumping data for table `template`
--

INSERT INTO `template` (`templateID`, `template`, `xml`, `permissionID`, `userID`, `createdDT`, `modifiedDT`, `description`, `tags`, `thumbnail`, `isSystem`, `retired`) VALUES
(1, 'Full Screen 16:9', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="450" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="450" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(2, 'Full Screen 16:10', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="500" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="500" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(3, 'Full Screen 4:3', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="800" height="600" bgcolor="#000000"><region id="47ff29524ce1b" width="800" height="600" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0),
(4, 'Full Screen 3:2', '<?xml version="1.0"?>\n<layout schemaVersion="1" width="720" height="480" bgcolor="#000000"><region id="47ff29524ce1b" width="720" height="480" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', 'fullscreen', NULL, 1, 0);

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`UserID`, `usertypeid`, `UserName`, `UserPassword`, `loggedin`, `lastaccessed`, `email`, `groupID`, `homepage`) VALUES
(1, 1, 'xibo_admin', '21232f297a57a5a743894a0e4a801fc3', 0, '2008-04-29 09:34:43', 'info@xibo.org.uk', 1, 'dashboard');

--
-- Dumping data for table `usertype`
--

INSERT INTO `usertype` (`usertypeid`, `usertype`) VALUES
(1, 'Super Admin'),
(2, 'Group Admin'),
(3, 'User');

--
-- Dumping data for table `version`
--

INSERT INTO `version` (`app_ver`) VALUES
('0.1.0');




--
-- Constraints for dumped tables
--

--
-- Constraints for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD CONSTRAINT `blacklist_ibfk_1` FOREIGN KEY (`MediaID`) REFERENCES `media` (`mediaID`),
  ADD CONSTRAINT `blacklist_ibfk_2` FOREIGN KEY (`DisplayID`) REFERENCES `display` (`displayid`);

--
-- Constraints for table `lklayoutmedia`
--
ALTER TABLE `lklayoutmedia`
  ADD CONSTRAINT `lklayoutmedia_ibfk_1` FOREIGN KEY (`mediaID`) REFERENCES `media` (`mediaID`),
  ADD CONSTRAINT `lklayoutmedia_ibfk_2` FOREIGN KEY (`layoutID`) REFERENCES `layout` (`layoutID`);

--
-- Constraints for table `lkpagegroup`
--
ALTER TABLE `lkpagegroup`
  ADD CONSTRAINT `lkpagegroup_ibfk_1` FOREIGN KEY (`pageID`) REFERENCES `pages` (`pageID`),
  ADD CONSTRAINT `lkpagegroup_ibfk_2` FOREIGN KEY (`groupID`) REFERENCES `group` (`groupID`);

--
-- Constraints for table `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`permissionID`) REFERENCES `permission` (`permissionID`);

--
-- Constraints for table `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`pagegroupID`) REFERENCES `pagegroup` (`pagegroupID`);

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`layoutID`) REFERENCES `layout` (`layoutID`);

--
-- Constraints for table `schedule_detail`
--
ALTER TABLE `schedule_detail`
  ADD CONSTRAINT `schedule_detail_ibfk_3` FOREIGN KEY (`displayID`) REFERENCES `display` (`displayid`),
  ADD CONSTRAINT `schedule_detail_ibfk_4` FOREIGN KEY (`layoutID`) REFERENCES `layout` (`layoutID`),
  ADD CONSTRAINT `schedule_detail_ibfk_5` FOREIGN KEY (`eventID`) REFERENCES `schedule` (`eventID`);

--
-- Constraints for table `template`
--
ALTER TABLE `template`
  ADD CONSTRAINT `template_ibfk_3` FOREIGN KEY (`userID`) REFERENCES `user` (`UserID`),
  ADD CONSTRAINT `template_ibfk_2` FOREIGN KEY (`permissionID`) REFERENCES `permission` (`permissionID`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_2` FOREIGN KEY (`usertypeid`) REFERENCES `usertype` (`usertypeid`),
  ADD CONSTRAINT `user_ibfk_3` FOREIGN KEY (`groupID`) REFERENCES `group` (`groupID`);
  
  
  
-- AS of R22

CREATE TABLE IF NOT EXISTS `lkmenuitemgroup` (
  `LkMenuItemGroupID` int(11) NOT NULL auto_increment,
  `GroupID` int(11) NOT NULL,
  `MenuItemID` int(11) NOT NULL,
  PRIMARY KEY  (`LkMenuItemGroupID`),
  KEY `GroupID` (`GroupID`),
  KEY `MenuItemID` (`MenuItemID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=7 ;

INSERT INTO `lkmenuitemgroup` (`LkMenuItemGroupID`, `GroupID`, `MenuItemID`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 14),
(5, 1, 15),
(6, 1, 16);

CREATE TABLE IF NOT EXISTS `menu` (
  `MenuID` smallint(6) NOT NULL auto_increment,
  `Menu` varchar(50) NOT NULL,
  PRIMARY KEY  (`MenuID`),
  UNIQUE KEY `Menu` (`Menu`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='A Menu (or collection of links)' AUTO_INCREMENT=5 ;

INSERT INTO `menu` (`MenuID`, `Menu`) VALUES
(2, 'Dashboard'),
(4, 'Management'),
(3, 'Region Manager'),
(1, 'Top Nav');

CREATE TABLE IF NOT EXISTS `menuitem` (
  `MenuItemID` int(11) NOT NULL auto_increment,
  `MenuID` smallint(6) NOT NULL,
  `PageID` int(11) NOT NULL,
  `Args` varchar(254) default NULL,
  `Text` varchar(20) NOT NULL,
  `Class` varchar(50) default NULL,
  `Img` varchar(254) default NULL,
  `Sequence` smallint(6) NOT NULL default '1',
  PRIMARY KEY  (`MenuItemID`),
  KEY `PageID` (`PageID`),
  KEY `MenuID` (`MenuID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=22 ;

INSERT INTO `menuitem` (`MenuItemID`, `MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`) VALUES
(1, 1, 2, NULL, 'Schedule', NULL, NULL, 1),
(2, 1, 5, NULL, 'Layout', NULL, NULL, 2),
(3, 1, 7, NULL, 'Library', NULL, NULL, 3),
(4, 1, 17, NULL, 'Management', NULL, NULL, 4),
(7, 4, 11, NULL, 'Displays', NULL, NULL, 1),
(8, 4, 15, NULL, 'Groups', NULL, NULL, 2),
(9, 4, 17, NULL, 'Users', NULL, NULL, 3),
(10, 4, 16, 'sp=log', 'Log', NULL, NULL, 4),
(11, 4, 18, NULL, 'License', NULL, NULL, 5),
(12, 4, 16, 'sp=sessions', 'Sessions', NULL, NULL, 6),
(13, 4, 14, NULL, 'Settings', NULL, NULL, 7),
(14, 2, 2, 'sp=month', 'Schedule', 'schedule_button', 'img/dashboard/scheduleview.png', 1),
(15, 2, 5, NULL, 'Layouts', 'playlist_button', 'img/dashboard/presentations.png', 2),
(16, 2, 7, NULL, 'Library', 'content_button', 'img/dashboard/content.png', 3),
(17, 2, 25, NULL, 'Templates', 'layout_button', 'img/dashboard/layouts.png', 4),
(18, 2, 17, NULL, 'Users', 'user_button', 'img/dashboard/users.png', 5),
(19, 2, 14, NULL, 'Settings', 'settings_button', 'img/dashboard/settings.png', 6),
(20, 2, 18, NULL, 'License', 'license_button', 'img/dashboard/license.png', 7),
(21, 3, 5, 'q=RegionOptions&[[layoutid]]&[[regionid]]', 'Edit your Display', 'playlist_button', 'img/dashboard/edit_content.png', 1);

ALTER TABLE `lkmenuitemgroup`
  ADD CONSTRAINT `lkmenuitemgroup_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkmenuitemgroup_ibfk_2` FOREIGN KEY (`MenuItemID`) REFERENCES `menuitem` (`MenuItemID`);

ALTER TABLE `menuitem`
  ADD CONSTRAINT `menuitem_ibfk_1` FOREIGN KEY (`MenuID`) REFERENCES `menu` (`MenuID`),
  ADD CONSTRAINT `menuitem_ibfk_2` FOREIGN KEY (`PageID`) REFERENCES `pages` (`pageID`);

-- Run the Below for code greater than R30
ALTER TABLE `module` ADD `RegionSpecific` TINYINT NOT NULL DEFAULT '1' AFTER `Enabled` ;
ALTER TABLE `module` ADD `ImageUri` VARCHAR( 254 ) NOT NULL AFTER `Description` ;
ALTER TABLE `module` ADD `SchemaVersion` INT NOT NULL DEFAULT '1' AFTER `ImageUri` ;


-- AS of R32
INSERT INTO `setting` (`settingid`,`setting` ,`value` ,`type` ,`helptext` ,`options` ,`cat` ,`userChange`)
VALUES (NULL , 'HELP_BASE', 'http://www.xibo.org.uk/manual/', 'text', NULL , NULL , 'path', '0');

DELETE FROM module;

INSERT INTO `module` (`ModuleID`, `Module`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`) VALUES
(1, 'Image', 1, 0, 'Images. PNG, JPG, BMP, GIF', 'img/forms/image.gif', 1),
(2, 'Video', 1, 0, 'Videos. WMV.', 'img/forms/video.gif', 1),
(3, 'Flash', 1, 0, 'Flash', 'img/forms/flash.gif', 1),
(4, 'Powerpoint', 1, 0, 'Powerpoint. PPT, PPS', 'img/forms/powerpoint.gif', 1),
(5, 'Webpage', 1, 1, 'Webpages.', 'img/forms/webpage.gif', 1),
(6, 'Ticker', 1, 1, 'RSS Ticker.', 'img/forms/ticker.gif', 1),
(7, 'Text', 1, 1, 'Text. With Directional Controls.', 'img/forms/text.gif', 1);

-- As of R50 ish
ALTER TABLE `display` CHANGE `license` `license` VARCHAR( 40 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

-- As of R55 ish
ALTER TABLE `version` ADD `XmdsVersion` SMALLINT NOT NULL ,
ADD `XlfVersion` SMALLINT NOT NULL ;

UPDATE `version` SET `XmdsVersion` = '1', `XlfVersion` = '1' LIMIT 1 ;

-- From ServerInstaller branch.
ALTER TABLE `version` ADD `DBVersion` INT NOT NULL DEFAULT '1';