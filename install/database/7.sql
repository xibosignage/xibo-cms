
INSERT INTO `pages` (
`pageID` ,
`name` ,
`pagegroupID`
)
VALUES (
NULL , 'stats', '9'
);

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`) 
SELECT '4', pageID, NULL, 'Statistics', NULL, NULL, '9' FROM pages WHERE name = 'stats';

ALTER TABLE `stat` ADD `Tag` VARCHAR( 254 ) NULL ;

ALTER TABLE `stat` ADD `Type` VARCHAR( 20 ) NOT NULL DEFAULT 'Media' AFTER `statID` ;

ALTER TABLE `stat` CHANGE `Type` `Type` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `stat` CHANGE `mediaID` `mediaID` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

UPDATE `version` SET `app_ver` = '1.0.3';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '7';
