
/* Will need to create a permission record for each display group against each display - so it remains as it is now (all users have permission to assign to all displays). */
INSERT INTO lkgroupdg (DisplayGroupID, GroupID)
SELECT displaygroup.DisplayGroupID, `group`.GroupID
FROM displaygroup
CROSS JOIN `group`;

/* Request URI is too short of passing a lot of parameters in GET. Maybe we should use POST more? */
ALTER TABLE `log` CHANGE `RequestUri` `RequestUri` VARCHAR( 2000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

/* Remove the groupID from the user record. */
ALTER TABLE `user` DROP FOREIGN KEY `user_ibfk_3` ;

ALTER TABLE `user` DROP `groupID` ;

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '21';
