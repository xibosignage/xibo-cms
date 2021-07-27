<?php
/**
 * Blacklist revamp migration, remove Blacklist table, change bandwidthtype.
 * Adds two new tables purge_list and player_faults.
 * Adds new setting for default purge_list ttl.
 * Adds new task that will clear expired entries in purge_list table.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddPurgeListTableMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        if ($this->hasTable('blacklist')) {
            $this->dropTable('blacklist');
        }

        $this->execute('UPDATE `bandwidthtype` SET `name` = \'Report Fault\' WHERE `name` = \'Blacklist\';');

        // Add Purge List table, this will store media information and expiry date, sent to Players in RF.
        $this->table('purge_list', ['id' => 'purgeListId'])
            ->addColumn('mediaId', 'integer')
            ->addColumn('storedAs', 'string')
            ->addColumn('expiryDate', 'datetime', ['null' => true, 'default'=> null])
            ->save();

        // Add Player Faults table, this will store alerts details sent by Players in Soap6.
        $this->table('player_faults', ['id' => 'playerFaultId'])
            ->addColumn('displayId', 'integer')
            ->addColumn('incidentDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('code', 'string', ['null' => true, 'default' => null])
            ->addColumn('reason', 'string', ['null' => true, 'default' => null])
            ->addColumn('scheduleId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('layoutId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('regionId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('mediaId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('widgetId', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('displayId', 'display', 'displayId')
            ->save();

        // Add a setting allowing users to set default Purge List TTL in days, default to a week.
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DEFAULT_PURGE_LIST_TTL\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'DEFAULT_PURGE_LIST_TTL',
                    'value' => 7,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }

        // Add a task that will clean-up Purge List table and remove entries older than specified TTL.
        $this->table('task')
            ->insert([
                [
                    'name' => 'Purge List Cleanup',
                    'class' => '\Xibo\XTR\PurgeListCleanupTask',
                    'options' => '[]',
                    'schedule' => '0 0 * * *',
                    'isActive' => '1',
                    'configFile' => '/tasks/purge-list-cleanup.task'
                ],
            ])->save();
    }
}
