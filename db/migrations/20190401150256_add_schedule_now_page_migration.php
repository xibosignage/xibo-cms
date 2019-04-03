<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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

class AddScheduleNowPageMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $pages = $this->table('pages');

        // add schedule now page
        if (!$this->fetchRow('SELECT * FROM pages WHERE name = \'schedulenow\'')) {
            $pages->insert([
                'name' => 'schedulenow',
                'title' => 'Schedule Now',
                'asHome' => 0
            ])->save();
        }

        // add permission to the schedule now page to every group and user, excluding "Everyone"
        $permissions = $this->table('permission');
        $scheduleNowPageId = $this->fetchRow('SELECT pageId FROM `pages` WHERE `name` = \'schedulenow\' ');
        $groupIds = $this->fetchAll('SELECT groupId FROM `group` WHERE `isEveryone` = 0 ');

        foreach ($groupIds as $groupId) {
            $permissions->insert([
                [
                    'entityId' => 1,
                    'groupId' => $groupId['groupId'],
                    'objectId' => $scheduleNowPageId[0],
                    'view' => 1,
                    'edit' => 0,
                    'delete' => 0
                ]
            ])->save();
        }
    }
}
