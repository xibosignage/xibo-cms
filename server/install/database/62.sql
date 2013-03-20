
ALTER TABLE  `user` ADD `CSPRNG` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE  `user` CHANGE  `UserPassword`  `UserPassword` VARCHAR( 40 ) NOT NULL;

UPDATE `version` SET `app_ver` = '1.5.0', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '62';
