<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

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
            $this->table('blacklist')->drop()->save();
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
            ->addColumn('expires', 'datetime', ['null' => true, 'default' => null])
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
                    'configFile' => '/tasks/purge-list-cleanup.task',
                    'pid' => 0,
                    'lastRunDt' => 0,
                    'lastRunDuration' => 0,
                    'lastRunExitCode' => 0
                ],
            ])->save();
    }
}
