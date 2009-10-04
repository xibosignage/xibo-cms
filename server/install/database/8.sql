/* Add the MD5 and FileSize as columns to the media table */
ALTER TABLE `media` ADD `MD5` VARCHAR( 32 ) NULL AFTER `storedAs` ,
ADD `FileSize` BIGINT NULL AFTER `MD5` ;

UPDATE `version` SET `app_ver` = '1.0.4';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '8';
