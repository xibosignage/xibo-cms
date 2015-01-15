INSERT INTO `module` (
`ModuleID` ,
`Module` ,
`Enabled` ,
`RegionSpecific` ,
`Description` ,
`ImageUri` ,
`SchemaVersion`
)
VALUES (
NULL , 'Embedded', '1', '1', 'Embedded HTML', 'img/forms/webpage.gif', '1'
);

UPDATE `version` SET `app_ver` = '1.0.2';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '6';