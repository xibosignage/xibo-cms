
ALTER TABLE  `user` ADD `CSPRNG` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE  `user` CHANGE  `UserPassword`  `UserPassword` VARCHAR( 128 ) NOT NULL;

DROP TABLE `bandwidth`;
CREATE TABLE IF NOT EXISTS `bandwidth` (
  `DisplayID` int(11) NOT NULL,
  `Type` tinyint(4) NOT NULL,
  `Month` int(11) NOT NULL,
  `Size` bigint(20) NOT NULL,
  PRIMARY KEY (`DisplayID`, `Type`, `Month`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

UPDATE `version` SET `app_ver` = '1.5.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '62';
