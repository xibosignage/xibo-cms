ALTER TABLE  `display` CHANGE  `storageAvailableSpace`  `storageAvailableSpace` BIGINT NULL DEFAULT NULL ,
CHANGE  `storageTotalSpace`  `storageTotalSpace` BIGINT NULL DEFAULT NULL;

UPDATE `version` SET `app_ver` = '1.7.3', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '87';