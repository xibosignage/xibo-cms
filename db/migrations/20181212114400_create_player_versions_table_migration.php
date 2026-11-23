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

/**
 * Class CreatePlayerVersionsTableMigration
 * Create a new table to store information about player versions
 * Install playersoftware widget
 * Remove apk,ipk from validExtensions in genericfiles module
 */
class CreatePlayerVersionsTableMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        if (!$this->hasTable('player_software')) {
            $versions = $this->table('player_software', ['id' => 'versionId']);

            $versions->addColumn('player_type', 'string', ['limit' => 20, 'default' => null, 'null' => true])
                ->addColumn('player_version', 'string', ['limit' => 15, 'default' => null, 'null' => true])
                ->addColumn('player_code', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL, 'null' => true])
                ->addColumn('mediaId', 'integer')
                ->addForeignKey('mediaId', 'media', 'mediaId')
                ->create();
        }

        // Add the player_software module
        $modules = $this->table('module');
        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'playersoftware\'')) {
            $modules->insert([
                'module' => 'playersoftware',
                'name' => 'Player Software',
                'enabled' => 1,
                'regionSpecific' => 0,
                'description' => 'A module for managing Player Versions',
                'schemaVersion' => 1,
                'validExtensions' => 'apk,ipk,wgt',
                'previewEnabled' => 0,
                'assignable' => 0,
                'render_as' => null,
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\PlayerSoftware',
                'defaultDuration' => 10
            ])->save();
        }

        // remove apk and ipk from valid extensions in generic file module
        $this->execute('UPDATE `module` SET validextensions = REPLACE(validextensions, \'apk,ipk\', \'\') WHERE module = \'genericfile\' LIMIT 1;');
    }
}
