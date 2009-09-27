
/* Will need to create a permission record for each display group against each display - so it remains as it is now (all users have permission to assign to all displays). */
INSERT INTO lkgroupdg (DisplayGroupID, GroupID)
SELECT displaygroup.DisplayGroupID, `group`.GroupID
FROM displaygroup
CROSS JOIN `group`;

/* VERSION UPDATE */
/* Set the version table, etc */
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '21';
