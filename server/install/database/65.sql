INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) 
VALUES (NULL, 'SCHEDULE_WITH_VIEW_PERMISSION', 'No', 'dropdown', 'Should users with View permissions on displays be allowed to schedule to them?', 'Yes|No', 'permissions', '1');

ALTER TABLE  `layout` ADD  `status` TINYINT NOT NULL DEFAULT  '0';

UPDATE `menuitem` SET `Args`='manual/index.php' WHERE `Text`='Manual';

UPDATE `version` SET `app_ver` = '1.5.2', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '65';