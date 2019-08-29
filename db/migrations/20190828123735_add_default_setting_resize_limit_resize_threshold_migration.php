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
 * Class AddDefaultSettingResizeLimitResizeThresholdMigration
 */
class AddDefaultSettingResizeLimitResizeThresholdMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Add a setting allowing users to maximum image resizing to 1920
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DEFAULT_RESIZE_LIMIT\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'DEFAULT_RESIZE_LIMIT',
                    'value' => 6000,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }

        // Add a setting allowing users set the limit to identify a large image dimensions
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DEFAULT_RESIZE_THRESHOLD\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'DEFAULT_RESIZE_THRESHOLD',
                    'value' => 1920,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }
    }
}

