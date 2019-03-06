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
 * Class ForgottenPasswordReminderMigration
 */
class ForgottenPasswordReminderMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DEFAULT_USERGROUP\'')) {
            $this->execute('
                INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) 
                  VALUES (\'DEFAULT_USERGROUP\', \'1\', \'text\', \'The default User Group for new Users\', \'1\', \'users\', 1, \'Default User Group\', \'\', 4, \'\', 1, \'int\');
            ');
        }

        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'PASSWORD_REMINDER_ENABLED\'')) {
            $this->execute('
                INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) 
                  VALUES (\'PASSWORD_REMINDER_ENABLED\', \'Off\', \'dropdown\', \'Is password reminder enabled?\', \'On|On except Admin|Off\', \'users\', 1, \'Password Reminder\', \'\', 50, \'Off\', 1, \'string\');
            ');
        }

        $table = $this->table('user');
        if (!$table->hasColumn('isPasswordChangeRequired')) {
            $table
                ->addColumn('isPasswordChangeRequired', 'integer',
                    ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->save();
        }
    }
}
