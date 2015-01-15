UPDATE `setting` SET `options` = 'User|Group Admin|Super Admin' WHERE `setting` = 'defaultUsertype';

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `version` SET `app_ver` = '1.2.1', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '26';
