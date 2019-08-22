<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Phinx\Migration\AbstractMigration;

/**
 * Class AddStatsMigrationTaskMigration
 */
class AddStatsMigrationTaskMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $table = $this->table('task');
        if (!$this->fetchRow('SELECT * FROM `task` WHERE name = \'Statistics Migration\'')) {
            $table->insert([
                [
                    'name' => 'Statistics Migration',
                    'class' => '\Xibo\XTR\StatsMigrationTask',
                    'options' => '{"killSwitch":"0","numberOfRecords":"5000","numberOfLoops":"10","pauseBetweenLoops":"1","optimiseOnComplete":"1"}',
                    'schedule' => '*/10 * * * * *',
                    'isActive' => '1',
                    'configFile' => '/tasks/stats-migration.task'
                ],
            ])->save();
        }
    }
}
