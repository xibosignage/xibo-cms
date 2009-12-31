INSERT INTO `pages` (`pageID`, `name`, `pagegroupID`) VALUES (26, 'fault', '10');

INSERT INTO `menuitem` (`MenuItemID`, `MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`) VALUES (NULL, '4', '26', NULL, 'Report Fault', NULL, NULL, '8');

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
NULL , 'SERVER_MODE', 'Production', 'dropdown', 'This should only be set if you want to display the maximum allowed error messaging through the user interface. <br /> Useful for capturing critical php errors and environment issues.', 'Production|Test', 'error', '1'
);

INSERT INTO `resolution` (
`resolutionID` ,
`resolution` ,
`width` ,
`height`
)
VALUES (
NULL , '3:4 Monitor', '600', '800'
), (
NULL , '2:3 Tv', '480', '720'
), (
NULL , '10:16 Widescreen', '500', '800'
), (
NULL , '9:16 HD Widescreen', '450', '800'
);


INSERT INTO `template` (`template`, `xml`, `permissionID`, `userID`, `createdDT`, `modifiedDT`, `description`, `tags`, `thumbnail`, `isSystem`, `retired`) VALUES
('Portrait - 10:16', '<?xml version="1.0"?>\n<layout width="500" height="800" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524ae1b" width="500" height="800" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0),
('Portrait - 9:16', '<?xml version="1.0"?>\n<layout width="450" height="800" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524be1b" width="450" height="800" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0),
('Portrait - 3:4', '<?xml version="1.0"?>\n<layout width="600" height="800" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524ce1b" width="600" height="800" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0),
('Portrait - 2:3', '<?xml version="1.0"?>\n<layout width="480" height="720" bgcolor="#000000" background="" schemaVersion="1"><region id="47ff2f524de1b" width="480" height="720" top="0" left="0"/></layout>\n', 3, 1, '2008-01-01 01:00:00', '2008-01-01 01:00:00', '', '', NULL, 1, 0);

ALTER TABLE `version` CHANGE `app_ver` `app_ver` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
UPDATE `version` SET `app_ver` = '1.0.0-final';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '4';
