<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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
 * Class PlayerUpgradeAndOverrideConfigMigration
 * Add Player Software to Pages
 * Remove version_instructions column from Display table
 * Add overrideConfig column to display table
 * Add default profile for Tizen
 */
class PlayerUpgradeAndOverrideConfigMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $pages = $this->table('pages');
        $displayTable = $this->table('display');
        $displayProfileTable = $this->table('displayprofile');

        // add Player Software page
        if (!$this->fetchRow('SELECT * FROM pages WHERE name = \'playersoftware\'')) {
            $pages->insert([
                'name' => 'playersoftware',
                'title' => 'Player Software',
                'asHome' => 0
            ])->save();
        }

        $displayTableModified = false;

        // remove version_instructions from display table
        if ($displayTable->hasColumn('version_instructions')) {
            $displayTable->removeColumn('version_instructions');
            $displayTableModified = true;
        }

        // add overrideConfig column to display table
        if (!$displayTable->hasColumn('overrideConfig')) {
            $displayTable->addColumn('overrideConfig', 'text');
            $displayTableModified = true;
        }

        if ($displayTableModified) {
            $displayTable->save();
        }

        // Get system user
        $user = $this->fetchRow("SELECT userId FROM `user` WHERE userTypeId = 1");

        // add default display profile for tizen
        if (!$this->fetchRow('SELECT * FROM displayprofile WHERE type = \'sssp\' AND isDefault = 1')) {
            $displayProfileTable->insert([
                'name' => 'Tizen',
                'type' => 'sssp',
                'config' => '[]',
                'userId' => $user['userId'],
                'isDefault' => 1
            ])->save();
        }
    }
}
