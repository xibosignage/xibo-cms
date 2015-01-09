ALTER TABLE  `user` CHANGE  `UserName`  `UserName` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

UPDATE `version` SET `app_ver` = '1.7.0-rc1', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '83';