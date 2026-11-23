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

class OldUpgradeStep130Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 130;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $schedule = $this->table('schedule');
                $schedule
                    ->addColumn('syncTimezone', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->save();

                $this->execute('UPDATE `permissionentity` SET entity = \'Xibo\\Entity\\Notification\' WHERE entity = \'XiboEntityNotification\';');

                if (!$this->fetchRow('
                    SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                        AND `table_name` = \'oauth_clients%\' AND referenced_table_name = \'user\';')) {

                    $this->execute('UPDATE `oauth_clients` SET userId = (SELECT userId FROM `user` WHERE userTypeId = 1 LIMIT 1)
                       WHERE userId NOT IN (SELECT userId FROM `user`);');

                    $this->execute('ALTER TABLE `oauth_clients` ADD CONSTRAINT oauth_clients_user_UserID_fk FOREIGN KEY (userId) REFERENCES `user` (UserID);');
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
