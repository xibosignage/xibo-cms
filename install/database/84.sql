
INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
 'CALENDAR_TYPE', 'Gregorian', 'dropdown', 'Which Calendar Type should the CMS use?', 'Gregorian|Jalali', 'regional', 1, 'Calendar Type',  '',  '50',  'Gregorian',  '1',  'string'
);

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`) VALUES
(NULL, 'clock', 'Clock', '1', '1', 'Display a Clock', 'forms/library.gif', '1', '', '1', '1', 'html');

INSERT INTO `displayprofile` (`name`, `type`, `config`, `isdefault`, `userid`)
VALUES ('Windows', 'windows', '[]', '1', '1'), ('Android', 'android', '[]', '1', '1');

UPDATE `version` SET `app_ver` = '1.7.0', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '84';