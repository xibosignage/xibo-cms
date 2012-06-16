ALTER TABLE  `schedule_detail` CHANGE  `layoutID`  `CampaignID` INT( 11 ) NOT NULL;

ALTER TABLE  `schedule_detail` ADD FOREIGN KEY (  `CampaignID` ) REFERENCES  `campaign` (
`CampaignID`
) ON DELETE RESTRICT ON UPDATE RESTRICT ;

ALTER TABLE  `schedule` CHANGE  `layoutID`  `CampaignID` INT( 11 ) NOT NULL;

ALTER TABLE  `schedule` ADD FOREIGN KEY (  `CampaignID` ) REFERENCES  `campaign` (
`CampaignID`
) ON DELETE RESTRICT ON UPDATE RESTRICT ;

DROP TABLE lklayoutgroup;

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`) VALUES (NULL, 'shellcommand', 'Shell Command', '1', '1', 'Execute a shell command on the client', 'img/forms/shellcommand.gif', '1', NULL);

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'LIBRARY_SIZE_LIMIT_KB', '0', 'text', 'The Limit for the Library Size in KB', NULL, 'content', '0'), (NULL, 'MONTHLY_XMDS_TRANSFER_LIMIT_KB', '0', 'text', 'XMDS Transfer Limit in KB/month', NULL, 'general', '0');

CREATE TABLE IF NOT EXISTS `bandwidth` (
  `BandwidthID` int(11) NOT NULL AUTO_INCREMENT,
  `DateTime` int(11) NOT NULL,
  `Type` tinyint(4) NOT NULL,
  `DisplayID` int(11) NOT NULL,
  `Size` int(11) NOT NULL,
  PRIMARY KEY (`BandwidthID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE  `user` ADD  `Retired` TINYINT NOT NULL DEFAULT  '0';

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) VALUES (NULL, 'DEFAULT_LANGUAGE', 'en_GB', 'text', 'The default language to use', NULL, 'general', '1');

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '47';