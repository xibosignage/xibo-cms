<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep132Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 132;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('UPDATE `schedule` SET toDt = 2147483647 WHERE toDt = 2556057600;');

                $this->execute('UPDATE `permission` SET 
                  entityId = (
                      SELECT entityId 
                        FROM permissionentity 
                       WHERE entity = \'Xibo\\Entity\\DisplayGroup\'), 
                  objectId = (
                      SELECT lkdisplaydg.DisplayGroupID 
                        FROM `lkdisplaydg` 
                        INNER JOIN `displaygroup` ON `displaygroup`.DisplayGroupID = `lkdisplaydg`.DisplayGroupID 
                          AND `displaygroup`.IsDisplaySpecific = 1 
                       WHERE permission.objectId = `lkdisplaydg`.DisplayID) 
                 WHERE entityId IN (SELECT entityId FROM permissionentity WHERE entity = \'Xibo\\Entity\\Display\');');

                $this->execute('DELETE FROM `permissionentity` WHERE `entity` = \'Xibo\\Entity\\Display\';');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
