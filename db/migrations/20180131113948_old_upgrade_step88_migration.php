<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep88Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 88;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $auditLog = $this->table('auditlog', ['id' => 'logId']);
                $auditLog->addColumn('logDate', 'integer')
                    ->addColumn('userId', 'integer')
                    ->addColumn('message', 'string', ['limit' => 255])
                    ->addColumn('entity', 'string', ['limit' => 50])
                    ->addColumn('entityId', 'integer')
                    ->addColumn('objectAfter', 'text')
                    ->save();

                $this->execute('INSERT INTO `pages` (`name`, `pagegroupID`) SELECT \'auditlog\', pagegroupID FROM `pagegroup` WHERE pagegroup.pagegroup = \'Reports\';');

                $group = $this->table('group');
                if (!$group->hasColumn('libraryQuota')) {
                    $group->addColumn('libraryQuota', 'integer', ['null' => true])
                        ->save();
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
