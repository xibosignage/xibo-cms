UPDATE `setting` SET `type` = 'timezone' WHERE `setting`.`settingid` =11 LIMIT 1 ;

UPDATE `version` SET `DBVersion` = '3' WHERE CONVERT( `version`.`app_ver` USING utf8 ) = '0.1.0' AND `version`.`XmdsVersion` =1 AND `version`.`XlfVersion` =1 AND `version`.`DBVersion` =1 LIMIT 1 ;
