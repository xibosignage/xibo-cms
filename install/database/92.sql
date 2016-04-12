
INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES
('CDN_URL', '', 'text', 'Content Delivery Network Address for serving file requests to Players', '', 'network', 0, 'CDN Address', '', 33, '', 0, 'text');

ALTER TABLE  `datasetcolumn` CHANGE  `ListContent`  `ListContent` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `stat` ADD INDEX Type (`displayID`, `end`, `Type`);

UPDATE `version` SET `app_ver` = '1.7.7', `XmdsVersion` = 4, `XlfVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '92';