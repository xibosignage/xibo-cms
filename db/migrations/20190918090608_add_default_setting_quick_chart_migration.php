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
 * Class AddDefaultSettingQuickChartMigration
 */
class AddDefaultSettingQuickChartMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Add a setting to allow report converted to pdf
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'QUICK_CHART_URL\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'QUICK_CHART_URL',
                    'value' => 'https://quickchart.io',
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }
    }
}


