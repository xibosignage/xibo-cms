ALTER TABLE  `display` CHANGE  `storageAvailableSpace`  `storageAvailableSpace` BIGINT NULL DEFAULT NULL ,
CHANGE  `storageTotalSpace`  `storageTotalSpace` BIGINT NULL DEFAULT NULL;

INSERT INTO  `setting` (`setting` ,`value` ,`fieldType` ,`helptext` ,`options` ,`cat` ,`userChange` ,`title` ,`validation` ,`ordering` ,`default` ,`userSee` ,`type`)
VALUES (
  'PROXY_EXCEPTIONS', '', 'text', 'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.', '', 'network', 1, 'Proxy Exceptions',  '',  '32',  '',  '1',  'text'
);

UPDATE `version` SET `app_ver` = '1.7.3', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '87';