<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Content Sync changes
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class ContentSyncMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('syncgroup', ['id' => 'syncGroupId'])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('ownerId', 'integer')
            ->addColumn('modifiedBy', 'integer', ['null' => true, 'default' => null])
            ->addColumn('syncPublisherPort', 'integer', ['default' => 9590])
            ->addColumn('leadDisplayId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('folderId', 'integer', ['default' => 1])
            ->addColumn('permissionsFolderId', 'integer', ['default' => 1])
            ->addForeignKey('folderId', 'folder', 'folderId')
            ->addForeignKey('ownerId', 'user', 'userId')
            ->addForeignKey('leadDisplayId', 'display', 'displayId')
            ->create();

        $this->table('display')
            ->addColumn('syncGroupId', 'integer', ['default' => null, 'null' => true])
            ->addForeignKey('syncGroupId', 'syncgroup', 'syncGroupId')
            ->save();

        $this->table('permissionentity')
            ->insert([
                ['entity' => 'Xibo\Entity\SyncGroup']
            ])
            ->save();

        $this->table('schedule_sync', ['id' => false, 'primary_key' => ['eventId', 'displayId']])
            ->addColumn('eventId', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addForeignKey('eventId', 'schedule', 'eventId')
            ->addForeignKey('displayId', 'display', 'displayId')
            ->addForeignKey('layoutId', 'layout', 'layoutId')
            ->create();

        $this->table('schedule')
            ->addColumn('syncGroupId', 'integer', ['default' => null, 'null' => true])
            ->addForeignKey('syncGroupId', 'syncgroup', 'syncGroupId')
            ->save();
    }
}
