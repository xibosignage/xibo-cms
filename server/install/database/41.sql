CREATE TABLE IF NOT EXISTS `lklayoutgroup` (
  `LkLayoutGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `LayoutID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkLayoutGroupID`),
  KEY `LayoutID` (`LayoutID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `lklayoutgroup`
  ADD CONSTRAINT `lklayoutgroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lklayoutgroup_ibfk_1` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`);



UPDATE `version` SET `app_ver` = '1.3.0', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '41';