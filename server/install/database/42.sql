INSERT INTO `menuitem` (`MenuID`, `PageID`, `Text`, `Sequence`)
SELECT 4, PageID, 'Applications', 12 FROM `pages` WHERE `name` = 'oauth';

INSERT INTO `menuitem` (`menuID`, `pageID`, `Text`, `sequence`)
SELECT '4', pageID, 'DataSets', '6'
  FROM pages
 WHERE `name` = 'dataset';

UPDATE `version` SET `app_ver` = '1.3.1', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '42';