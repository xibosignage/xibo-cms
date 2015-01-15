
ALTER TABLE  `module` ADD  `PreviewEnabled` TINYINT NOT NULL DEFAULT  '1';

UPDATE `version` SET `app_ver` = '1.4.1', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '51';