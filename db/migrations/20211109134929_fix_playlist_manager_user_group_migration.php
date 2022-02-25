<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
 * Fix default homepage for Playlist Manager User group
 * Fix feature for old Playlist Dashboard User group
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class FixPlaylistManagerUserGroupMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->execute('UPDATE `group` SET defaultHomepageId = \'playlistdashboard.view\' WHERE `group` = \'Playlist Manager\'');

        // get current set of features assigned to Playlist Dashboard User's group
        $row = $this->fetchRow('SELECT features FROM `group` WHERE `group` = \'Playlist Dashboard User\' ');
        if (is_array($row)) {
            $features = json_decode($row[0]);

            // add the feature to Playlist Dashboard
            $features[] = 'dashboard.playlist';

            // Update features and default homepage for Playlist Dashboard User UserGroup
            $this->execute('UPDATE `group` SET features = \'' . json_encode($features) . '\' WHERE `group` = \'Playlist Dashboard User\' ');
            $this->execute('UPDATE `group` SET defaultHomepageId = \'playlistdashboard.view\' WHERE `group` = \'Playlist Dashboard User\' ');
        }
    }
}
