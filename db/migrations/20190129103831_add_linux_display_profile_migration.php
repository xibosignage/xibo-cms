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
 * Class AddLinuxDisplayProfileMigration
 */
class AddLinuxDisplayProfileMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Check to see if we already have a Linux default display profile. if not, add it.
        if (!$this->fetchRow('SELECT * FROM displayprofile WHERE type = \'linux\' AND isDefault = 1')) {
            // Get system user
            $user = $this->fetchRow('SELECT userId FROM `user` WHERE userTypeId = 1');

            $table = $this->table('displayprofile');
            $table->insert([
                'name' => 'Linux',
                'type' => 'linux',
                'config' => '[]',
                'userId' => $user['userId'],
                'isDefault' => 1
            ])->save();
        }
    }
}
