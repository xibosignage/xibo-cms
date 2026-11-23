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

class AddSystemUserSettingMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'SYSTEM_USER\'')) {

            // get System User
            $getSystemUserQuery = $this->query('SELECT userId FROM user WHERE userTypeId = 1 ORDER BY userId LIMIT 1');
            $getSystemUserResult = $getSystemUserQuery->fetchAll(PDO::FETCH_ASSOC);

            // if for some reason there are no super admin Users in the CMS, ensure that migration does not fail.
            if (count($getSystemUserResult) >= 1) {
                $userId = $getSystemUserResult[0]['userId'];

                $this->table('setting')->insert([
                    [
                        'setting' => 'SYSTEM_USER',
                        'value' => $userId,
                        'userSee' => 1,
                        'userChange' => 1
                    ]
                ])->save();
            }
        }
    }
}
