INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`) VALUES
(NULL, 'clock', 'Clock', '1', '1', 'Display a Clock', 'forms/library.gif', '1', '', '1', '1', 'html');


UPDATE `version` SET `app_ver` = '1.7.1', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '85';