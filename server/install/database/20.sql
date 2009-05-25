INSERT INTO `pages` (
`pageID` ,
`name` ,
`pagegroupID`
)
VALUES (
NULL, 'help', '2',
NULL, 'clock', 2
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
ADD INDEX `DisplayGroupID` ( `DisplayGroupID` ) 