<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

class FixDuplicateModuleFilesMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // get System User
        $getSystemUserQuery = $this->query('SELECT userId FROM user WHERE userTypeId = 1 ORDER BY userId LIMIT 1 ');
        $getSystemUserResult = $getSystemUserQuery->fetchAll(PDO::FETCH_ASSOC);

        $userId = $getSystemUserResult[0]['userId'];

        // set System User as owner of the module files
        $this->execute('UPDATE `media` SET userId = ' . $userId . ' WHERE moduleSystemFile = 1 AND userId = 0; ');
    }
}
