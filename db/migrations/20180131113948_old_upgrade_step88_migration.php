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

class OldUpgradeStep88Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 88;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $auditLog = $this->table('auditlog', ['id' => 'logId']);
                $auditLog->addColumn('logDate', 'integer')
                    ->addColumn('userId', 'integer')
                    ->addColumn('message', 'string', ['limit' => 255])
                    ->addColumn('entity', 'string', ['limit' => 50])
                    ->addColumn('entityId', 'integer')
                    ->addColumn('objectAfter', 'text')
                    ->save();

                $this->execute('INSERT INTO `pages` (`name`, `pagegroupID`) SELECT \'auditlog\', pagegroupID FROM `pagegroup` WHERE pagegroup.pagegroup = \'Reports\';');

                $group = $this->table('group');
                if (!$group->hasColumn('libraryQuota')) {
                    $group->addColumn('libraryQuota', 'integer', ['null' => true])
                        ->save();
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
