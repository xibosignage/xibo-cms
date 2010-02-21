INSERT INTO `pagegroup` (`pagegroupID`, `pagegroup`) VALUES (NULL, 'Web Services');

INSERT INTO `pages` (`name`, `pagegroupID`)
SELECT 'oauth', pagegroupID FROM pagegroup WHERE pagegroup = 'Web Services';

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';

UPDATE  `version` SET  `app_ver` =  '1.1.1', `XmdsVersion` =  '2', `DBVersion` =  '22';