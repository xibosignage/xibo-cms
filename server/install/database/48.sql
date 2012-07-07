INSERT INTO `module` (
`ModuleID` ,
`Module` ,
`Name` ,
`Enabled` ,
`RegionSpecific` ,
`Description` ,
`ImageUri` ,
`SchemaVersion` ,
`ValidExtensions`
)
VALUES (
NULL ,  'localvideo',  'Local Video',  '0',  '1',  'Play a video locally stored on the client',  'img/forms/video.gif',  '1', NULL
);


UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '48';