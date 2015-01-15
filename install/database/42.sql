INSERT INTO `menuitem` (`MenuID`, `PageID`, `Text`, `Sequence`)
SELECT 4, PageID, 'Applications', 12 FROM `pages` WHERE `name` = 'oauth';

INSERT INTO `menuitem` (`menuID`, `pageID`, `Text`, `sequence`)
SELECT '4', pageID, 'DataSets', '6'
  FROM pages
 WHERE `name` = 'dataset';

ALTER TABLE  `module` ADD  `Name` VARCHAR( 50 ) NOT NULL AFTER  `Module`;

UPDATE `module` SET `Name` = `Module`;

INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`) VALUES (NULL, 'datasetview', 'Data Set', '1', '1', 'A view on a DataSet', 'img/forms/datasetview.gif', '1', NULL);

UPDATE  `module` SET  `ImageUri` =  'img/forms/counter.gif' WHERE  `module`.`ModuleID` =10 LIMIT 1 ;

UPDATE `version` SET `app_ver` = '1.3.1', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '42';