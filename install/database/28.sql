
ALTER TABLE  `display` ALTER  `MediaInventoryStatus` SET DEFAULT '0';

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.2.3', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '28';
