ALTER TABLE  `module` ADD  `assignable` TINYINT NOT NULL DEFAULT  '1';

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`) VALUES (NULL, 'genericfile', 'Generic File', '1', '0', 'A generic file to be stored in the library', 'forms/library.gif', '1', 'apk,js,html,htm', '0', '0');

ALTER TABLE  `media` CHANGE  `type`  `type` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

UPDATE `version` SET `app_ver` = '1.6.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '67';
