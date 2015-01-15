INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) 
VALUES (NULL, 'DEFAULT_LAT', '51.504', 'text', 'The Latitude to apply for any Geo aware Previews', NULL, 'general', '1');

INSERT INTO `setting` (`settingid`, `setting`, `value`, `type`, `helptext`, `options`, `cat`, `userChange`) 
VALUES (NULL, 'DEFAULT_LONG', '-0.104', 'text', 'The Longitude to apply for any Geo aware Previews', NULL, 'general', '1');

UPDATE `version` SET `app_ver` = '1.5.1', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '64';