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
 * Class MakeDisplayLicenseColumnUniqueMigration
 */
class MakeDisplayLicenseColumnUniqueMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // query the database and look for duplicate entries
        $duplicatesData = $this->query('SELECT displayId, license FROM display WHERE license IN ( SELECT license FROM display GROUP BY license HAVING count(*) > 1) GROUP BY displayId;');
        $rowsDuplicatesData = $duplicatesData->fetchAll(PDO::FETCH_ASSOC);
        // only execute this code if any duplicates were found
        if (count($rowsDuplicatesData) > 0) {
            $licences = [];
            $filtered = [];
            // create new array with license as the key
            foreach ($rowsDuplicatesData as $row) {
                $licences[$row['license']][] = $row['displayId'];
            }
            // iterate through the array and remove first element from each of the arrays with displayIds
            foreach ($licences as $licence) {
                array_shift($licence);
                $filtered[] = $licence;
            }
            // iterate through our new filtered array, that only contains displayIds that should be removed and execute the SQL DELETE statements
            foreach ($filtered as $item) {
                foreach ($item as $displayId) {
                    $this->execute('DELETE FROM lkdisplaydg WHERE displayId = ' . $displayId);
                    $this->execute('DELETE FROM display WHERE `displayId` = ' . $displayId);
                }
            }
        }

        // add unique index to license column
        $table = $this->table('display');
        $table->addIndex(['license'], ['unique' => true])->update();
    }
}