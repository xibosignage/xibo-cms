INSERT INTO `pages` (
`pageID` ,
`name` ,
`pagegroupID`
)
VALUES (
'help', '2',
),(
'clock', 2
);

CREATE TABLE IF NOT EXISTS `help` (
  `HelpID` int(11) NOT NULL auto_increment,
  `Topic` varchar(254) NOT NULL,
  `Category` varchar(254) NOT NULL default 'General',
  `Link` varchar(254) NOT NULL,
  PRIMARY KEY  (`HelpID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;

INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) VALUES
(1, 'Layout', 'General', 'http://wiki.xibo.org.uk/index.php?title=Layouts_-_General_Help&printable=true'),
(2, 'Content', 'General', 'http://wiki.xibo.org.uk/index.php?title=Content_-_General_Help&printable=true'),
(4, 'Schedule', 'General', 'http://wiki.xibo.org.uk/index.php?title=Schedule_-_General_Help&printable=true'),
(5, 'Group', 'General', 'http://wiki.xibo.org.uk/index.php?title=Group_-_General_Help&printable=true'),
(6, 'Admin', 'General', 'http://wiki.xibo.org.uk/index.php?title=Admin_-_Settings_Help&printable=true'),
(7, 'Report', 'General', 'http://wiki.xibo.org.uk/index.php?title=Reports_-_General_Help&printable=true');


/* New page for display groups */
INSERT INTO `pages` (
`pageID` ,
`name` ,
`pagegroupID`
)
VALUES (
NULL , 'displaygroup', '7'
);

/* New menu item for display groups */
INSERT INTO `menuitem` (
`MenuItemID` ,
`MenuID` ,
`PageID` ,
`Args` ,
`Text` ,
`Class` ,
`Img` ,
`Sequence`
)
VALUES (
NULL , '4', '29', NULL , 'Display Groups', NULL , NULL , '2'
);

/* Create display groups. 20.php will handle adding a IsDisplaySpecific group for each display and linking it. */
CREATE TABLE IF NOT EXISTS `displaygroup` (
  `DisplayGroupID` int(11) NOT NULL auto_increment,
  `DisplayGroup` varchar(50) NOT NULL,
  `Description` varchar(254) default NULL,
  `IsDisplaySpecific` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`DisplayGroupID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

CREATE TABLE IF NOT EXISTS `lkdisplaydg` (
  `LkDisplayDGID` int(11) NOT NULL auto_increment,
  `DisplayGroupID` int(11) NOT NULL,
  `DisplayID` int(11) NOT NULL,
  PRIMARY KEY  (`LkDisplayDGID`),
  KEY `DisplayGroupID` (`DisplayGroupID`),
  KEY `DisplayID` (`DisplayID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

ALTER TABLE `lkdisplaydg`
  ADD CONSTRAINT `lkdisplaydg_ibfk_1` FOREIGN KEY (`DisplayGroupID`) REFERENCES `displaygroup` (`DisplayGroupID`),
  ADD CONSTRAINT `lkdisplaydg_ibfk_2` FOREIGN KEY (`DisplayID`) REFERENCES `display` (`displayid`);

/* Last accessed date on display table need to be a timestamp */
UPDATE display SET lastaccessed = NULL;
ALTER TABLE `display` CHANGE `lastaccessed` `lastaccessed` INT NULL DEFAULT NULL;
UPDATE display SET lastaccessed = UNIX_TIMESTAMP() - 86400;

/* Permissions for Display Groups against Groups */
CREATE TABLE `lkgroupdg` (
`LkGroupDGID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`GroupID` INT NOT NULL ,
`DisplayGroupID` INT NOT NULL
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci 

ALTER TABLE `lkgroupdg` ADD INDEX ( `GroupID` )  ;
 
ALTER TABLE `lkgroupdg` ADD INDEX ( `DisplayGroupID` )  ;

ALTER TABLE `lkgroupdg` ADD FOREIGN KEY ( `GroupID` ) REFERENCES `group` (
`groupID`
);

ALTER TABLE `lkgroupdg` ADD FOREIGN KEY ( `DisplayGroupID` ) REFERENCES `displaygroup` (
`DisplayGroupID`
);

/* Will need to create a permission record for each display group against each display - so it remains as it is now (all users have permission to assign to all displays). */
INSERT INTO lkgroupdg (DisplayGroupID, GroupID)
SELECT displaygroup.DisplayGroupID, `group`.GroupID
FROM displaygroup
CROSS JOIN `group`;

/* SCHEDULE */
/* Change the display list to a display group list */
ALTER TABLE `schedule` CHANGE `displayID_list` `DisplayGroupIDs` VARCHAR( 254 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'A list of the display group ids for this event' ;

ALTER TABLE `schedule` 
		ADD `FromDT` BIGINT NOT NULL DEFAULT '0',
		ADD `ToDT` BIGINT NOT NULL DEFAULT '0',
		ADD `recurrence_range_temp` BIGINT NULL ;

UPDATE schedule SET 
	FromDT = UNIX_TIMESTAMP(start), 
	ToDT = UNIX_TIMESTAMP(end), 
	recurrence_range_temp = CASE WHEN recurrence_range IS NULL THEN NULL ELSE UNIX_TIMESTAMP(recurrence_range) END

ALTER TABLE `schedule`
  DROP `recurrence_range`,
  DROP `start`,
  DROP `end`;
  
 ALTER TABLE `schedule` CHANGE `recurrence_range_temp` `recurrence_range` BIGINT( 20 ) NULL DEFAULT NULL  ;

/* Schedule Detail */ 
ALTER TABLE `schedule_detail` DROP FOREIGN KEY `schedule_detail_ibfk_3` ;

ALTER TABLE `schedule_detail` DROP FOREIGN KEY `schedule_detail_ibfk_4` ;

ALTER TABLE `schedule_detail` ADD FOREIGN KEY ( `layoutID` ) REFERENCES `layout` (
`layoutID`
);

ALTER TABLE `schedule_detail` DROP FOREIGN KEY `schedule_detail_ibfk_5` ;

ALTER TABLE `schedule_detail` ADD FOREIGN KEY ( `eventID` ) REFERENCES `schedule` (
`eventID`
);

ALTER TABLE `schedule_detail` CHANGE `displayID` `DisplayGroupID` INT( 11 ) NOT NULL  ;
 
ALTER TABLE `schedule_detail` DROP INDEX `displayid` ,
ADD INDEX `DisplayGroupID` ( `DisplayGroupID` ) ;

ALTER TABLE `schedule_detail` 
	ADD `FromDT` BIGINT NOT NULL DEFAULT '0',
	ADD `ToDT` BIGINT NOT NULL DEFAULT '0';

UPDATE `schedule_detail` SET 
	FromDT = UNIX_TIMESTAMP( starttime ) ,
	ToDT = UNIX_TIMESTAMP( endtime ) ;
	
ALTER TABLE `schedule_detail`
  DROP `starttime`,
  DROP `endtime`;
  
UPDATE schedule_detail SET FromDT = 946684800 WHERE FromDT = 0;  
  
ALTER TABLE `schedule_detail` DROP INDEX `schedule_detail_ibfk_3`;
ALTER TABLE `schedule_detail` DROP INDEX `IM_SDT_DisplayID`;  

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.1.0';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '20';
