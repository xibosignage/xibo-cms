ALTER TABLE  `permission` ADD  `PublicView` TINYINT NOT NULL DEFAULT  '0',
ADD  `PublicEdit` TINYINT NOT NULL DEFAULT  '0';

ALTER TABLE  `permission` ADD  `GroupView` TINYINT NOT NULL DEFAULT  '0',
ADD  `GroupEdit` TINYINT NOT NULL DEFAULT  '0';

UPDATE `permission` SET  `permission` =  'Group View',
`PublicView` =  '0', `GroupView` = '1' WHERE  `permission`.`permissionID` =2 LIMIT 1;

UPDATE `permission` SET  `permission` =  'Public View',
`PublicView` =  '1', `GroupView` = '1' WHERE  `permission`.`permissionID` =3 LIMIT 1;

INSERT INTO `permission` (
`permissionID` ,
`permission` ,
`PublicView` ,
`PublicEdit` ,
`GroupView` ,
`GroupEdit`
)
VALUES (
'4',  'Group Edit',  '0',  '0', '1', '1'
), (
'5',  'Public Edit',  '1',  '1', '1', '1'
);

ALTER TABLE  `permission` ADD  `DisplayOrder` TINYINT NOT NULL DEFAULT  '0';

UPDATE `permission` SET  `DisplayOrder` =  '1' WHERE  `permission`.`permissionID` =1 LIMIT 1 ;

UPDATE `permission` SET  `DisplayOrder` =  '2' WHERE  `permission`.`permissionID` =2 LIMIT 1 ;

UPDATE `permission` SET  `DisplayOrder` =  '3' WHERE  `permission`.`permissionID` =4 LIMIT 1 ;

UPDATE `permission` SET  `DisplayOrder` =  '4' WHERE  `permission`.`permissionID` =3 LIMIT 1 ;

UPDATE `permission` SET  `DisplayOrder` =  '5' WHERE  `permission`.`permissionID` =5 LIMIT 1 ;

UPDATE `version` SET `app_ver` = '1.3.0', `XmdsVersion` = 2;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '41';