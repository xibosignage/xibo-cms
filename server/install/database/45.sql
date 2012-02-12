INSERT INTO `help` (
`HelpID` ,
`Topic` ,
`Category` ,
`Link`
)
VALUES (
NULL , 'User', 'ChangePassword', 'http://wiki.xibo.org.uk/wiki/Manual:Administration:Users#Change_Password'
);

UPDATE `version` SET `app_ver` = '1.3.3', `XmdsVersion` = 3;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '45';