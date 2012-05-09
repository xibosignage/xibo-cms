ALTER TABLE  `schedule_detail` DROP FOREIGN KEY  `schedule_detail_ibfk_6` ;
ALTER TABLE  `schedule` DROP FOREIGN KEY  `schedule_ibfk_1` ;

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '46';