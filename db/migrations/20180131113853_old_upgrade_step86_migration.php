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

class OldUpgradeStep86Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 86;

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
                        [
                            'setting' => 'DASHBOARD_LATEST_NEWS_ENABLED',
                            'title' => 'Enable Latest News?',
                            'helptext' => 'Should the Dashboard show latest news? The address is provided by the theme.',
                            'value' => '1',
                            'fieldType' => 'checkbox',
                            'options' => '',
                            'cat' => 'general',
                            'userChange' => '1',
                            'type' => 'checkbox',
                            'validation' => '',
                            'ordering' => '110',
                            'default' => '1',
                            'userSee' => '1',
                        ],
                        [
                            'setting' => 'LIBRARY_MEDIA_DELETEOLDVER_CHECKB',
                            'title' => 'Default for \"Delete old version of Media\" checkbox. Shown when Editing Library Media.',
                            'helptext' => 'Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.',
                            'value' => 'Unchecked',
                            'fieldType' => 'dropdown',
                            'options' => 'Checked|Unchecked',
                            'cat' => 'defaults',
                            'userChange' => '1',
                            'type' => 'dropdown',
                            'validation' => '',
                            'ordering' => '50',
                            'default' => 'Unchecked',
                            'userSee' => '1',
                        ]
                    ])
                    ->save();

                // Update a setting
                $this->execute('UPDATE `setting` SET `type` = \'checkbox\', `fieldType` = \'checkbox\' WHERE setting = \'SETTING_LIBRARY_TIDY_ENABLED\' OR setting = \'SETTING_IMPORT_ENABLED\';');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
