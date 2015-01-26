
INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
 'CALENDAR_TYPE', 'Gregorian', 'dropdown', 'Which Calendar Type should the CMS use?', 'Gregorian|Jalali', 'regional', 1, 'Calendar Type',  '',  '50',  'Gregorian',  '1',  'string'
);

UPDATE `version` SET `app_ver` = '1.7.0', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '84';