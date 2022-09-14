<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
 * Add home folder to users.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class UserHomeFolderMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Add a new field for the home folder
        $this->table('user')
            ->addColumn('homeFolderId', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'null' => false,
                'default' => 1,
            ])
            ->addForeignKey('homeFolderId', 'folder', 'folderId')
            ->save();
    }
}
