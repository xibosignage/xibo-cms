<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Phinx\Migration\AbstractMigration;

/**
 * Migration for adding missing indexes
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class MissingIndexesMigration extends AbstractMigration
{
    public function change(): void
    {
        $region = $this->table('region');
        if (!$region->hasForeignKey('layoutId')) {
            // Take care of orphaned regions
            $this->execute('DELETE FROM `region` WHERE `layoutId` NOT IN (SELECT `layoutId` FROM `layout`)');

            // Add the FK
            $region
                ->addForeignKey('layoutId', 'layout', 'layoutId')
                ->save();
        }

        $playlist = $this->table('playlist');
        if (!$playlist->hasIndex('regionId')) {
            $playlist
                ->addIndex('regionId')
                ->save();
        }
    }
}
