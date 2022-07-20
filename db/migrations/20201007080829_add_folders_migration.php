<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

class AddFoldersMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // create Folders table
        $table = $this->table('folder', ['id' => 'folderId']);
        $table->addColumn('folderName', 'string')
            ->addColumn('parentId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('isRoot', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('children', 'text', ['default' => null, 'null' => true])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1, 'null' => true])
            ->create();

        // insert records to Folders table for root objects
        $table->insert([
            [
                'folderId' => 1,
                'folderName' => '/',
                'parentId' => null,
                'isRoot' => 1,
                'children' => null,
                'permissionsFolderId' => null
            ]
        ])->save();

        $this->table('permissionentity')
            ->insert([
                ['entity' => 'Xibo\Entity\Folder']
            ])
            ->save();

        // update media/playlist to make sure there aren't any date times with a default value which is nolonger
        // acceptable.
        $this->execute('UPDATE `media` SET createdDt = \'1970-01-01 00:00:00\' WHERE createdDt < \'2000-01-01\'');
        $this->execute('UPDATE `media` SET modifiedDt = \'1970-01-01 00:00:00\' WHERE modifiedDt < \'2000-01-01\'');
        $this->execute('UPDATE `playlist` SET createdDt = \'1970-01-01 00:00:00\' WHERE createdDt < \'2000-01-01\'');
        $this->execute('UPDATE `playlist` SET modifiedDt = \'1970-01-01 00:00:00\' WHERE modifiedDt < \'2000-01-01\'');

        // add folderId column to root object tables
        $this->table('media')
            ->changeColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->changeColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('folderId', 'integer', ['default' => 1])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1])
            ->addForeignKey('folderId', 'folder', 'folderId')
            ->save();

        $this->table('campaign')
            ->addColumn('folderId', 'integer', ['default' => 1])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1])
            ->addForeignKey('folderId', 'folder', 'folderId')
            ->save();

        $this->table('playlist')
            ->changeColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->changeColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('folderId', 'integer', ['default' => 1])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1])
            ->addForeignKey('folderId', 'folder', 'folderId')
            ->save();

        $this->table('displaygroup')
            ->addColumn('folderId', 'integer', ['default' => 1])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1])
            ->addForeignKey('folderId', 'folder', 'folderId')
            ->save();

        $this->table('dataset')
            ->addColumn('folderId', 'integer', ['default' => 1])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1])
            ->addForeignKey('folderId', 'folder', 'folderId')
            ->save();
    }
}
