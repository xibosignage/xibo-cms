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

UPDATE `version` SET `DBVersion` = '4';