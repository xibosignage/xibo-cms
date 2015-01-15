INSERT INTO `pages` (
`name` ,
`pagegroupID`
)
VALUES (
'manual', '2'
);

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
SELECT '2', pageID, 'http://wiki.xibo.org.uk/wiki/Manual:TOC', 'Manual', 'help_button', 'img/dashboard/help.png', '10' FROM pages WHERE name = 'manual';

UPDATE `module` SET `ValidExtensions` = 'ppt,pps,pptx' WHERE `module`.`ModuleID` =4 LIMIT 1 ;

UPDATE `version` SET `app_ver` = '1.0.5';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '9';
