
ALTER TABLE `module` ADD `ValidExtensions` VARCHAR( 254 ) NULL ;

UPDATE `module` SET `ValidExtensions` = 'jpg,jpeg,png,bmp,gif' WHERE `module`.`ModuleID` =1 LIMIT 1 ;

UPDATE `module` SET `ValidExtensions` = 'wmv,avi,mpg,mpeg' WHERE `module`.`ModuleID` =2 LIMIT 1 ;

UPDATE `module` SET `ValidExtensions` = 'swf' WHERE `module`.`ModuleID` =3 LIMIT 1 ;

UPDATE `module` SET `ValidExtensions` = 'ppt,pps' WHERE `module`.`ModuleID` =4 LIMIT 1 ;


/* Add the MD5 and FileSize as columns to the media table */
ALTER TABLE `media` ADD `MD5` VARCHAR( 32 ) NULL AFTER `storedAs` ,
ADD `FileSize` BIGINT NULL AFTER `MD5` ;


UPDATE `version` SET `app_ver` = '1.0.4';
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '8';
