

UPDATE `version` SET `app_ver` = '1.3.0', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '41';