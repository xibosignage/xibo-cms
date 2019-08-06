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

class DisplayProfileCommandLinkFixMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // query the database and look for duplicate entries
        $duplicatesData = $this->query('SELECT commandId, displayProfileId FROM lkcommanddisplayprofile WHERE commandId IN ( SELECT commandId FROM lkcommanddisplayprofile GROUP BY commandId HAVING COUNT(*) > 1) ');
        $rowsDuplicatesData = $duplicatesData->fetchAll(PDO::FETCH_ASSOC);
        
        // only execute this code if any duplicates were found
        if (count($rowsDuplicatesData) > 0) {
            $duplicates = [];
            // create new array with displayProfileId as the key
            foreach ($rowsDuplicatesData as $row) {
                $duplicates[$row['displayProfileId']][] = $row['commandId'];
            }

            // iterate through the arrays get unique commandIds, calculate the limit and execute Delete query.
            foreach ($duplicates as $displayProfileId => $commandId) {

                // commandId is an array, get the unique Ids from it
                $uniqueCommandIds = array_unique($commandId);

                // iterate through our array of uniqueCommandIds and calculate the LIMIT for our SQL Delete statement
                foreach ($uniqueCommandIds as $uniqueCommandId) {
                    // create an array with commandId as the key and count of duplicate as value
                    $limits = array_count_values($commandId);

                    // Limits is an array with uniqueCommandId as the key and count of duplicate as value, we want to leave one record, hence we subtract 1
                    $limit = $limits[$uniqueCommandId] - 1;

                    // if we have any duplicates then run the delete statement, for each displayProfileId with uniqueCommandId and calculated limit per uniqueCommandId
                    if ($limit > 0) {
                        $this->execute('DELETE FROM lkcommanddisplayprofile WHERE commandId = ' . $uniqueCommandId . ' AND displayProfileId = ' . $displayProfileId . ' LIMIT ' . $limit);
                    }
                }
            }
        }

        // add the primary key for CMS upgrades, fresh CMS Installations will have it correctly added in installation migration.
        if (!$this->fetchRow('SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME = \'lkcommanddisplayprofile\' AND CONSTRAINT_TYPE = \'PRIMARY KEY\' AND CONSTRAINT_SCHEMA = Database();')) {
            $this->execute('ALTER TABLE lkcommanddisplayprofile ADD PRIMARY KEY (commandId, displayProfileId);');
        }
    }
}
