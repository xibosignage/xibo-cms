
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

INSERT INTO `pages` (`name`, `pagegroupID`)
  SELECT 'auditlog', pagegroupID FROM `pagegroup` WHERE pagegroup.pagegroup = 'Reports';

INSERT INTO `menuitem` (MenuID, PageID, Args, Text, Class, Img, Sequence, External)
  SELECT 9, PageID, NULL, 'Audit Trail', NULL, NULL, 2, 0
  FROM `pages`
  WHERE name = 'auditlog';

ALTER TABLE  `group` ADD  `libraryQuota` INT NULL;

UPDATE `version` SET `app_ver` = '1.7.4a', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '88';