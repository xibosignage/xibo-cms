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
 * Class EventLayoutPermissionSettingMigration
 */
class EventLayoutPermissionSettingMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function up()
    {
        // Check to see if the mail_from_name setting exists
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'SCHEDULE_SHOW_LAYOUT_NAME\'')) {
            $this->execute('INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES (\'SCHEDULE_SHOW_LAYOUT_NAME\', \'0\', \'checkbox\', \'If checked then the Schedule will show the Layout for existing events even if the logged in User does not have permission to see that Layout.\', null, \'permissions\', 1, \'Show event Layout regardless of User permission?\', \'\', 45, \'\', 1, \'checkbox\');');
        }
    }
}
