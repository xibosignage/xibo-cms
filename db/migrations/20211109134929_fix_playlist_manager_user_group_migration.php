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
