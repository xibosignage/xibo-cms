UPDATE `help` SET `Link` = REPLACE(REPLACE(Link, '.php', ''), 'manual/content/', 'manual/single.php?p=');
UPDATE `help` SET `Link` = REPLACE(`Link`, 'p=dashboard/', 'p=coreconcepts/');

UPDATE `version` SET `app_ver` = '1.6.0-rc1', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '66';