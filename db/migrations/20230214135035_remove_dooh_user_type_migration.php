<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Remove Dooh User Type and User showContentFrom
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class RemoveDoohUserTypeMigration extends AbstractMigration
{
    public function change()
    {
        // Update all DOOH users to super admins
        $this->execute('UPDATE `user` SET userTypeId = 1 WHERE userTypeId = 4');

        $this->execute('DELETE FROM `usertype` WHERE `userType` = \'DOOH\' ');

        $userTable = $this->table('user');
        if ($userTable->hasColumn('showContentFrom')) {
            $userTable
                ->removeColumn('showContentFrom')
                ->save();
        }
    }
}
