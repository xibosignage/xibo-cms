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
 * Class AddGlobalStatSettingMigration
 */
class AddGlobalStatSettingMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $dateTime = new \DateTime();
        $earlierMonth = $dateTime->modify( '-1 month' )->format( 'Y-m-d' );

        $result = $this->fetchRow('SELECT EXISTS (SELECT * FROM `stat` where `stat`.end >  \'' . $earlierMonth . '\' LIMIT 1)');
        $table = $this->table('setting');

        // if there are no stats recorded in last 1 month then layout stat is Off
        if ($result[0] <= 0 ) {
            $table
                ->insert([
                    [
                        'setting' => 'LAYOUT_STATS_ENABLED_DEFAULT',
                        'value' => '0',
                        'userSee' => 1,
                        'userChange' => 1
                    ]
                ])
                ->save();
        } else {
            $table
                ->insert([
                    [
                        'setting' => 'LAYOUT_STATS_ENABLED_DEFAULT',
                        'value' => '1',
                        'userSee' => 1,
                        'userChange' => 1
                    ]
                ])
                ->save();
        }


        // Media and widget stat is always set to Inherit
        $table
            ->insert([
                [
                    'setting' => 'DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT',
                    'value' => 'Individual',
                    'userSee' => 1,
                    'userChange' => 1
                ],
                [
                    'setting' => 'MEDIA_STATS_ENABLED_DEFAULT',
                    'value' => 'Inherit',
                    'userSee' => 1,
                    'userChange' => 1
                ],
                [
                    'setting' => 'WIDGET_STATS_ENABLED_DEFAULT',
                    'value' => 'Inherit',
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])
            ->save();
    }
}