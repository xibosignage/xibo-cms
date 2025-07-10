<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
 * Add a new Sony Bravia display profile to display profile table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

class AddSonyBraviaToDisplayProfileTableMigration extends AbstractMigration
{
    public function change(): void
    {
        $user = $this->fetchRow('SELECT userId FROM `user` WHERE userTypeId = 1');

        $this->table('displayprofile')
            ->insert([
                'name' => 'Sony Bravia',
                'type' => 'android',
                'config' => '[]',
                'userId' => $user['userId'],
                'isDefault' => 0
            ])
            ->save();
    }
}
