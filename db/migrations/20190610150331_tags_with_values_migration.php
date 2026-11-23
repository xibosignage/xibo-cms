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

class TagsWithValuesMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $tagTable = $this->table('tag');

        // add new columns to the tag table
        if (!$tagTable->hasColumn('isSystem')) {

            $tagTable
                ->addColumn('isSystem', 'integer', ['default' => 0, 'null' => false])
                ->addColumn('options', 'text', ['default' => null, 'null' => true])
                ->addColumn('isRequired', 'integer', ['default' => 0, 'null' => false])
                ->save();
        }

        // set isSystem flag on these tags
        $this->execute('UPDATE `tag` SET `isSystem` = 1 WHERE tag IN (\'template\', \'background\', \'thumbnail\', \'imported\')');

        // add value column to lktag tables
        $lktagTables = ["lktagcampaign", "lktagdisplaygroup", "lktaglayout", "lktagmedia", "lktagplaylist"];

        foreach ($lktagTables as $lktagTable) {
            $table = $this->table($lktagTable);

            if(!$table->hasColumn('value')) {
                $table
                    ->addColumn('value', 'text', ['default' => null, 'null' => true])
                    ->save();
            }
        }
    }
}
