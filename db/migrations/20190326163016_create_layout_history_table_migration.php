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
            ->addColumn('publishedDate', 'datetime', ['null' => true, 'default' => null])
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
