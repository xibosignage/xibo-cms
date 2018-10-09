<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep130Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 130;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $schedule = $this->table('schedule');
                $schedule
                    ->addColumn('syncTimezone', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->save();

                $this->execute('UPDATE `permissionentity` SET entity = \'Xibo\\Entity\\Notification\' WHERE entity = \'XiboEntityNotification\';');

                if (!$this->fetchRow('
                    SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                        AND `table_name` = \'oauth_clients%\' AND referenced_table_name = \'user\';')) {

                    $this->execute('UPDATE `oauth_clients` SET userId = (SELECT userId FROM `user` WHERE userTypeId = 1 LIMIT 1)
                       WHERE userId NOT IN (SELECT userId FROM `user`);');

                    $this->execute('ALTER TABLE `oauth_clients` ADD CONSTRAINT oauth_clients_user_UserID_fk FOREIGN KEY (userId) REFERENCES `user` (UserID);');
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
