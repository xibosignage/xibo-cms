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
 * Class CreatePlayerVersionsTableMigration
 * Create a new table to store information about player versions
 */
class CreatePlayerVersionsTableMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $versions = $this->table('player_software', ['id' => 'versionId']);
        $versions->addColumn('player_type', 'string', ['limit' => 20, 'default' => null, 'null' => true])
            ->addColumn('player_version', 'string', ['limit' => 15, 'default' => null, 'null' => true])
            ->addColumn('player_code', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL, 'null' => true])
            ->addColumn('mediaId', 'integer')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->create();
    }
}
