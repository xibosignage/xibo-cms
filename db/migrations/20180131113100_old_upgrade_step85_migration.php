<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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

class OldUpgradeStep85Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 85;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                $display = $this->table('display');

                if (!$display->hasColumn('storageAvailableSpace')) {
                    $display
                        ->addColumn('storageAvailableSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                        ->addColumn('storageTotalSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                        ->save();
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
