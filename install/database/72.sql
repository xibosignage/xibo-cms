
ALTER TABLE `schedule_detail` ADD INDEX ( `FromDT` , `ToDT` ) ;

UPDATE `version` SET `app_ver` = '1.6.4', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '72';
