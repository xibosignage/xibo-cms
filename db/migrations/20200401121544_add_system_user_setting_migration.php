<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
