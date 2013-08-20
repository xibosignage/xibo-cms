

ALTER TABLE  `layout` ADD  `status` TINYINT NOT NULL DEFAULT  '0';

UPDATE `version` SET `app_ver` = '1.5.2', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '65';