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

class OldUpgradeStep92Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 92;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $settings = $this->table('setting');
                $settings
                    ->insert([
                        'setting' => 'CDN_URL',
                        'title' => 'CDN Address',
                        'helptext' => 'Content Delivery Network Address for serving file requests to Players',
                        'value' => '',
                        'fieldType' => 'text',
                        'options' => '',
                        'cat' => 'network',
                        'userChange' => '0',
                        'type' => 'string',
                        'validation' => '',
                        'ordering' => '33',
                        'default' => '',
                        'userSee' => '0',
                    ])
                    ->save();

                $this->execute('ALTER TABLE  `datasetcolumn` CHANGE  `ListContent`  `ListContent` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');

                $this->execute('ALTER TABLE `stat` ADD INDEX Type (`displayID`, `end`, `Type`);');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
