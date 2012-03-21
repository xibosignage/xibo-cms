
ALTER TABLE `display` ADD `LastWakeOnLanCommandSent` INT NULL;

ALTER TABLE `display` ADD `WakeOnLan` TINYINT NOT NULL DEFAULT 0;

ALTER TABLE `display` ADD `WakeOnLanTime` VARCHAR(5) NULL;

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '45';