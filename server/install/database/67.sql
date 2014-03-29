ALTER TABLE  `module` ADD  `assignable` TINYINT NOT NULL DEFAULT  '1';

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`) VALUES (NULL, 'genericfile', 'Generic File', '1', '0', 'A generic file to be stored in the library', 'forms/library.gif', '1', 'apk,js,html,htm', '0', '0');

ALTER TABLE  `media` CHANGE  `type`  `type` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

CREATE TABLE IF NOT EXISTS `lkmediadisplaygroup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mediaid` int(11) NOT NULL,
  `displaygroupid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='File associations directly to Display Groups' AUTO_INCREMENT=1 ;

ALTER TABLE `display` ADD `version_instructions` VARCHAR( 255 ) NULL,
	ADD  `client_type` VARCHAR( 20 ) NULL ,
	ADD  `client_version` VARCHAR( 5 ) NULL ,
	ADD  `client_code` SMALLINT NULL;

INSERT INTO `help` (`Topic`, `Category`, `Link`) VALUES
('DisplayGroup', 'FileAssociations', 'manual/single.php?p=admin/fileassociations');

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) 
	VALUES 
		(NULL, 'PROXY_HOST', '', 'text', 'The Proxy URL', NULL, 'general', '1'),
		(NULL, 'PROXY_PORT', '', 'text', 'The Proxy Port', NULL, 'general', '1'),
		(NULL, 'PROXY_AUTH', '', 'text', 'The Authentication information for this proxy. username:password', NULL, 'general', '1');

UPDATE `version` SET `app_ver` = '1.6.0-rc2', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '67';
