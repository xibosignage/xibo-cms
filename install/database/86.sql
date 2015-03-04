INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
  'DASHBOARD_LATEST_NEWS_ENABLED', '1', 'checkbox', 'Should the Dashboard show latest news? The address is provided by the theme.', '', 'general', 1, 'Enable Latest News?',  '',  '110',  '1',  '1',  'checkbox'
);

UPDATE `version` SET `app_ver` = '1.7.2', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '86';