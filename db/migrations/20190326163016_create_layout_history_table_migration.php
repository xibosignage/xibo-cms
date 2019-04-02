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

/**
 * Class CreateLayoutHistoryTableMigration
 */
class CreateLayoutHistoryTableMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $table = $this->table('layouthistory', ['id' => 'layoutHistoryId']);
        $table->addColumn('campaignId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addColumn('publishedDate', 'datetime')
            ->addForeignKey('campaignId', 'campaign', 'campaignId')
            ->create();

        // insert all published layoutIds and their corresponding campaignId in the layouthistory
        $this->execute('INSERT INTO `layouthistory` (campaignId, layoutId, publishedDate)  
                            SELECT T.campaignId, L.layoutId, L.modifiedDt
                            FROM layout L
                            INNER JOIN
                                (SELECT 
                                    lkc.layoutId, lkc.campaignId
                                FROM
                                    `campaign` C
                                INNER JOIN `lkcampaignlayout` lkc 
                                ON C.campaignId = lkc.campaignId
                                WHERE
                                    isLayoutSpecific = 1) T 
                            ON T.layoutId = L.layoutId
                            WHERE
                                L.parentId IS NULL;');
    }
}
