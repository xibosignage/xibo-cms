ALTER TABLE  `dataset` ADD  `LastDataEdit` INT NOT NULL DEFAULT  '0';

CREATE TABLE IF NOT EXISTS `lkdatasetlayout` (
  `LkDataSetLayoutID` int(11) NOT NULL AUTO_INCREMENT,
  `DataSetID` int(11) NOT NULL,
  `LayoutID` int(11) NOT NULL,
  `RegionID` varchar(50) NOT NULL,
  `MediaID` varchar(50) NOT NULL,
  PRIMARY KEY (`LkDataSetLayoutID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


UPDATE `version` SET `app_ver` = '1.6.2', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '70';
