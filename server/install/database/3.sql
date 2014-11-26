UPDATE `setting` SET `type` = 'timezone' WHERE `setting`.`settingid` =11 LIMIT 1 ;

ALTER TABLE `log` CHANGE `page` `page` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL  ;

UPDATE `version` SET `DBVersion` = '3';
