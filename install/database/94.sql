
UPDATE `module` SET `name` = 'Weather', `description` = 'Weather Powered by DarkSky' WHERE `Module` = 'forecastio';

UPDATE `version` SET `app_ver` = '1.7.9', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '94';