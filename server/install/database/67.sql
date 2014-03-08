ALTER TABLE  `module` ADD  `assignable` TINYINT NOT NULL DEFAULT  '1';

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`) VALUES (NULL, 'genericfile', 'Generic File', '1', '0', 'A generic file to be stored in the library', 'forms/library.gif', '1', 'apk,js,html,htm', '0', '0');

ALTER TABLE  `media` CHANGE  `type`  `type` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

CREATE TABLE IF NOT EXISTS `lkmediadisplaygroup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mediaid` int(11) NOT NULL,
  `displaygroupid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='File associations directly to Display Groups' AUTO_INCREMENT=1 ;

ALTER TABLE `display` ADD `version_instructions` VARCHAR( 255 ) NULL;

INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) VALUES
(1, 'DisplayGroup', 'FileAssociations', 'manual/single.php?p=admin/fileassociations');

UPDATE `version` SET `app_ver` = '1.6.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '67';
