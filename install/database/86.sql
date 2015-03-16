INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
  'DASHBOARD_LATEST_NEWS_ENABLED', '1', 'checkbox', 'Should the Dashboard show latest news? The address is provided by the theme.', '', 'general', 1, 'Enable Latest News?',  '',  '110',  '1',  '1',  'checkbox'
);

INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `type`, `validation`, `ordering`, `default`, `userSee`)
VALUES (
  'LIBRARY_MEDIA_DELETEOLDVER_CHECKB','Unchecked','dropdown','Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.','Checked|Unchecked','defaults',1,'Default for "Delete old version of Media" checkbox. Showen when Editing Library Media.','dropdown','',50,'Unchecked',1
);

INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
  'USE_INTL_DATEFORMAT', '0', 'checkbox', 'Should dates be internationalised where possible.', '', 'regional', 1, 'Show international dates?',  '',  '60',  '0',  '1',  'checkbox'
);

UPDATE `setting` SET `type` = 'checkbox', `fieldType` = 'checkbox' WHERE setting = 'SETTING_LIBRARY_TIDY_ENABLED' OR setting = 'SETTING_IMPORT_ENABLED';

UPDATE `version` SET `app_ver` = '1.7.2', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '86';