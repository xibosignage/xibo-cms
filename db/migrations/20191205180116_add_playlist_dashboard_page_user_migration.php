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
 * Class AddPlaylistDashboardPageUserMigration
 */
class AddPlaylistDashboardPageUserMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {

        $page = $this->execute(
            'INSERT INTO `pages` SET `name`=\'playlistdashboard\', `title`= \'Playlist Dashboard\', `asHome`=1;
                   ');
        $pageId = $this->getAdapter()->getConnection()->lastInsertId();

        $group = $this->execute(
            'INSERT INTO `group` SET `group`=\'Playlist Dashboard User\', `isUserSpecific`= 0, `isEveryone`= 0, `isSystemNotification`= 0;
                   ');
        $groupId = $this->getAdapter()->getConnection()->lastInsertId();

        // Set Playlist Dashboard Page Permission
        $this->execute('INSERT INTO `permission` (`entityId`, `groupId`, `objectId`, `view`) SELECT  1, '.$groupId.', '.$pageId.', 1');

        // Set Library Page  Permission - pageid = 5
        $this->execute('INSERT INTO `permission` (`entityId`, `groupId`, `objectId`, `view`) SELECT  1, '.$groupId.', 5, 1');

        // Set Users Page  Permission - pageid = 11
        $this->execute('INSERT INTO `permission` (`entityId`, `groupId`, `objectId`, `view`) SELECT  1, '.$groupId.', 11, 1');
    }
}

