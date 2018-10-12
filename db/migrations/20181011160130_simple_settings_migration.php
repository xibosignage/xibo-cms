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
