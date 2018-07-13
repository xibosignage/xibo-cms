<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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
 * Class AddWidgetSyncTaskMigration
 */
class AddWidgetSyncTaskMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Check to see if the mail_from_name setting exists
        if (!$this->fetchRow('SELECT * FROM `task` WHERE name = \'Widget Sync\'')) {
            $this->execute('INSERT INTO `task` SET `name`=\'Widget Sync\', `class`=\'\\\\Xibo\\\\XTR\\\\WidgetSyncTask\', `status`=2, `isActive`=1, `configFile`=\'/tasks/widget-sync.task\', `options`=\'{}\', `schedule`=\'*/3 * * * *\';');
        }
    }
}
