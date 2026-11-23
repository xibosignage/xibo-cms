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
