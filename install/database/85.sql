ALTER TABLE  `display` ADD  `storageAvailableSpace` INT NULL ,
ADD  `storageTotalSpace` INT NULL;

UPDATE `version` SET `app_ver` = '1.7.1', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '85';