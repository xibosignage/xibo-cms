
ALTER TABLE  `display` ADD  `MediaInventoryStatus` TINYINT NOT NULL DEFAULT '0' ,
ADD  `MediaInventoryXml` LONGTEXT NULL;

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.2.2', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '27';
