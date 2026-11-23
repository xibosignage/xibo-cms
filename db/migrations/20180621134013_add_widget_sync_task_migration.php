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
