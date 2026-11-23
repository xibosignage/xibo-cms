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

class OldUpgradeStep131Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 131;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES    (\'DISPLAY_PROFILE_STATS_DEFAULT\', \'0\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Default setting for Statistics Enabled?\', \'\', 70, \'0\', 1, \'checkbox\'),(\'DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Enable the option to report the current layout status?\', \'\', 80, \'0\', 1, \'checkbox\'),(\'DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Enable the option to set the screenshot interval?\', \'\', 90, \'0\', 1, \'checkbox\'),(\'DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT\', \'200\', \'number\', \'The default size in pixels for the Display Screenshots\', NULL, \'displays\', 1, \'Display Screenshot Default Size\', \'\', 100, \'200\', 1, \'int\'),(\'LATEST_NEWS_URL\', \'https://xibosignage.com/feed\', \'text\', \'RSS/Atom Feed to be displayed on the Status Dashboard\', \'\', \'general\', 0, \'Latest News URL\', \'\', 111, \'\', 0, \'string\');');

                $display = $this->table('display');
                $display->removeColumn('currentLayoutId')->save();

                $permissionEntity = $this->table('permissionentity');
                $permissionEntity->insert([
                    'entity' => 'Xibo\\Entity\\Display'
                ])->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
