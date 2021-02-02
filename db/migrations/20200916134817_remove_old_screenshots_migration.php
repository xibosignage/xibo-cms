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

class RemoveOldScreenshotsMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // add the task
        $table = $this->table('task');
        $table->insert([
            [
                'name' => 'Remove Old Screenshots',
                'class' => '\Xibo\XTR\RemoveOldScreenshotsTask',
                'options' => '[]',
                'schedule' => '0 0 * * * *',
                'isActive' => '1',
                'configFile' => '/tasks/remove-old-screenshots.task'
            ],
        ])->save();

        // Add the ttl setting
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DISPLAY_SCREENSHOT_TTL\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'DISPLAY_SCREENSHOT_TTL',
                    'value' => 0,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }
    }
}
