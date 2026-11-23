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
 * Class SimpleSettingsMigration
 */
class SimpleSettingsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Update all of our old "Checked|Unchecked" boxes to be proper checkboxes
        $this->execute('UPDATE `setting` SET `value` = 0 WHERE `value` = \'Unchecked\'');
        $this->execute('UPDATE `setting` SET `value` = 1 WHERE `value` = \'Checked\'');

        // Update all of our old "Yes|No" boxes to be proper checkboxes
        $this->execute('UPDATE `setting` SET `value` = 0 WHERE `value` = \'No\'');
        $this->execute('UPDATE `setting` SET `value` = 1 WHERE `value` = \'Yes\'');

        // Update all of our old "Off|On" boxes to be proper checkboxes (unless there are more than 2 options)
        $this->execute('UPDATE `setting` SET `value` = 0 WHERE `value` = \'Off\' AND `setting` NOT IN (\'MAINTENANCE_ENABLED\', \'PASSWORD_REMINDER_ENABLED\', \'SENDFILE_MODE\')');
        $this->execute('UPDATE `setting` SET `value` = 1 WHERE `value` = \'On\' AND `setting` NOT IN (\'MAINTENANCE_ENABLED\', \'PASSWORD_REMINDER_ENABLED\')');

        $table = $this->table('setting');
        $table
            ->removeColumn('type')
            ->removeColumn('title')
            ->removeColumn('default')
            ->removeColumn('fieldType')
            ->removeColumn('helpText')
            ->removeColumn('options')
            ->removeColumn('cat')
            ->removeColumn('validation')
            ->removeColumn('ordering')
            ->save();
    }
}
