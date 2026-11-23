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

class OldUpgradeStep134Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 134;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('INSERT INTO displayprofile (name, type, config, isdefault, userId) VALUES (\'webOS\', \'lg\', \'{}\', 1, 1)');
                $this->execute('INSERT INTO module (Module, Name, Enabled, RegionSpecific, Description, ImageUri, SchemaVersion, ValidExtensions, PreviewEnabled, assignable, render_as, settings, viewPath, class, defaultDuration) VALUES (\'notificationview\', \'Notification\', 1, 1, \'Display Notifications from the Notification Centre\', \'forms/library.gif\', 1, null, 1, 1, \'html\', null, \'../modules\', \'Xibo\\\\Widget\\\\NotificationView\', 10);');

                $group = $this->table('group');
                $group
                    ->addColumn('isDisplayNotification', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                    ->save();

                $this->execute('DELETE FROM `setting` WHERE setting = \'MAINTENANCE_ALERTS_FOR_VIEW_USERS\';');

                $linkTagDisplayGroup = $this->table('lktagdisplaygroup', ['id' => 'lkTagDisplayGroupId']);
                $linkTagDisplayGroup
                    ->addColumn('tagId', 'integer')
                    ->addColumn('displayGroupId', 'integer')
                    ->addIndex(['tagId', 'displayGroupId'], ['unique' => true])
                    ->save();

                $media = $this->table('media');
                $media
                    ->addColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
                    ->addColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
                    ->save();

                $this->execute('UPDATE `module` SET validextensions = CONCAT(validextensions, \',ipk\') WHERE module = \'genericfile\' LIMIT 1;');

                $this->execute('UPDATE `module` SET description = \'A module for showing Currency pairs and exchange rates\' WHERE module = \'currencies\';');

                $this->execute('UPDATE `module` SET description = \'A module for showing Stock quotes\' WHERE module = \'stocks\';');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
