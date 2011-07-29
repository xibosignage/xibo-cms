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

ALTER TABLE  `group` ADD  `IsEveryone` TINYINT NOT NULL DEFAULT  '0';

INSERT INTO `group` (
`groupID` ,
`group` ,
`IsUserSpecific` ,
`IsEveryone`
)
VALUES (
NULL ,  'Everyone',  '0',  '1'
);

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

ALTER TABLE `lkmediagroup`
  ADD CONSTRAINT `lkmediagroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lkmediagroup_ibfk_1` FOREIGN KEY (`MediaID`) REFERENCES `media` (`MediaID`);

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

ALTER TABLE `lklayoutmediagroup`
  ADD CONSTRAINT `lklayoutmediagroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lklayoutmediagroup_ibfk_1` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`);

CREATE TABLE IF NOT EXISTS `lktemplategroup` (
  `LkTemplateGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `TemplateID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `View` tinyint(4) NOT NULL DEFAULT '0',
  `Edit` tinyint(4) NOT NULL DEFAULT '0',
  `Del` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`LkTemplateGroupID`),
  KEY `TemplateID` (`TemplateID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `lktemplategroup`
  ADD CONSTRAINT `lktemplategroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lktemplategroup_ibfk_1` FOREIGN KEY (`TemplateID`) REFERENCES `template` (`TemplateID`);

ALTER TABLE  `layout` DROP  `permissionID`;

ALTER TABLE  `media` DROP FOREIGN KEY  `media_ibfk_1` ;
ALTER TABLE  `media` DROP  `permissionID`;

ALTER TABLE  `template` DROP FOREIGN KEY  `template_ibfk_2` ;
ALTER TABLE  `template` DROP  `permissionID`;

DROP TABLE  `permission`;

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

ALTER TABLE `lklayoutregiongroup`
  ADD CONSTRAINT `lklayoutregiongroup_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `group` (`groupID`),
  ADD CONSTRAINT `lklayoutregiongroup_ibfk_1` FOREIGN KEY (`LayoutID`) REFERENCES `layout` (`layoutID`);

INSERT INTO lktemplategroup (TemplateID, GroupID, View)
SELECT TemplateID, GroupId, 1
  FROM template
    CROSS JOIN (SELECT GroupID, `Group` FROM `group` WHERE IsEveryone = 1) `group`
 WHERE IsSystem = 1;

INSERT INTO `setting` (
`settingid` ,
`setting` ,
`value` ,
`type` ,
`helptext` ,
`options` ,
`cat` ,
`userChange`
)
VALUES (
NULL ,  'REGION_OPTIONS_COLOURING',  'media',  'dropdown', NULL ,  'Media Colouring|Permissions Colouring',  'permissions',  '1'
);

UPDATE `version` SET `app_ver` = '1.3.0', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '41';