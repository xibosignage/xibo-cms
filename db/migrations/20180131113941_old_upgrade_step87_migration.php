<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep87Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 87;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $settings = $this->table('setting');
                $settings
                    ->insert([
                        'setting' => 'PROXY_EXCEPTIONS',
                        'title' => 'Proxy Exceptions',
                        'helptext' => 'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.',
                        'value' => '1',
                        'fieldType' => 'text',
                        'options' => '',
                        'cat' => 'network',
                        'userChange' => '1',
                        'type' => 'string',
                        'validation' => '',
                        'ordering' => '32',
                        'default' => '',
                        'userSee' => '1',
                    ])
                    ->save();

                // If we haven't run step85 during this migration, then we will want to update our storageAvailable columns
                // Change to big ints.
                $display = $this->table('display');
                $display
                    ->changeColumn('storageAvailableSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->changeColumn('storageTotalSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
