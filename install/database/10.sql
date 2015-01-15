INSERT INTO `pagegroup` (
`pagegroupID` ,
`pagegroup`
)
VALUES (
NULL ,  'Templates'
);

ALTER TABLE  `pages` DROP FOREIGN KEY  `pages_ibfk_1` ;

ALTER TABLE  `pages` ADD FOREIGN KEY (  `pagegroupID` ) REFERENCES  `pagegroup` (
`pagegroupID`
);

INSERT INTO `pages` (name, pagegroupid)
SELECT 'resolution', pagegroupid FROM `pagegroup` WHERE pagegroup = 'Templates';

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
SELECT '4', pageID, NULL, 'Resolutions', NULL, NULL, '10' FROM pages WHERE name = 'resolution';

INSERT INTO `menuitem` (`MenuID`, `PageID`, `Args`, `Text`, `Class`, `Img`, `Sequence`)
SELECT '4', pageID, NULL, 'Templates', NULL, NULL, '10' FROM pages WHERE name = 'template';

UPDATE `module` SET  `Module` =  'PowerPoint' WHERE  `module`.`ModuleID` = 4 LIMIT 1 ;

ALTER TABLE  `layout` CHANGE  `xml`  `xml` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

UPDATE `version` SET `app_ver` = '1.0.6';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '10';