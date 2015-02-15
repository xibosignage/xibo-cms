ALTER TABLE  `layout` ADD  `width` DECIMAL NOT NULL ,
ADD  `height` DECIMAL NOT NULL ,
ADD  `backgroundColor` VARCHAR( 25 ) NULL ,
ADD  `schemaVersion` TINYINT NOT NULL;


UPDATE `version` SET `app_ver` = '1.8.0-alpha', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '120';