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

/**
 * Class AddPlaylistDashboardPageUserMigration
 */
class AddPlaylistDashboardPageUserMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {

        $result = $this->fetchRow('SELECT entityId FROM `permissionentity` WHERE entity LIKE \'%Page\' LIMIT 1 ');
        $pageEntityId = $result['entityId'];

        $result = $this->fetchRow('SELECT pageId FROM `pages` WHERE `pages`.name = \'user\' LIMIT 1 ');
        $userPageId = $result['pageId'];

        $result = $this->fetchRow('SELECT pageId FROM `pages` WHERE `pages`.name = \'library\' LIMIT 1 ');
        $libraryPageId = $result['pageId'];

        // Create playlist dashboard page
        $this->execute('
            INSERT INTO `pages` 
                SET `name`=\'playlistdashboard\', `title`= \'Playlist Dashboard\', `asHome`=1;
        ');

        // Get playlist dashboard pageId
        $playlistDashboardPageId = $this->getAdapter()->getConnection()->lastInsertId();

        // Create playlist dashboard user group
        $this->execute('
            INSERT INTO `group` 
                SET `group`=\'Playlist Dashboard User\', `isUserSpecific`= 0, `isEveryone`= 0, `isSystemNotification`= 0;
        ');

        // Get playlist dashboard user groupId
        $groupId = $this->getAdapter()->getConnection()->lastInsertId();

        // Set Permission for playlist dashboard user group
        $permission = $this->table('permission');
        $permission->insert([
            [
                'entityId' => $pageEntityId,
                'groupId' => $groupId,
                'objectId' => $playlistDashboardPageId,
                'view' => 1,
                'edit' => 0,
                'delete' => 0
            ],
            [
                'entityId' => $pageEntityId,
                'groupId' => $groupId,
                'objectId' => $libraryPageId,
                'view' => 1,
                'edit' => 0,
                'delete' => 0
            ],
            [
                'entityId' => $pageEntityId,
                'groupId' => $groupId,
                'objectId' => $userPageId,
                'view' => 1,
                'edit' => 0,
                'delete' => 0
            ],
        ])->save();
    }
}

