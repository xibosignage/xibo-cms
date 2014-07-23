
ALTER TABLE  `module` ADD  `render_as` VARCHAR( 10 ) NULL;
ALTER TABLE  `module` ADD  `settings` TEXT NULL;

UPDATE `resolution` SET enabled = 0;

ALTER TABLE  `resolution` ADD  `version` TINYINT NOT NULL DEFAULT  '1';
ALTER TABLE  `resolution` ADD  `enabled` TINYINT NOT NULL DEFAULT  '1'

UPDATE `version` SET `app_ver` = '1.7.0-alpha', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '80';
