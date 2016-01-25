ALTER TABLE `version` ENGINE=InnoDB;
ALTER TABLE `transition` ENGINE=InnoDB;
ALTER TABLE `session` ENGINE=InnoDB;
ALTER TABLE `log` ENGINE=InnoDB;

UPDATE `version` SET `app_ver` = '1.7.6', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '91';