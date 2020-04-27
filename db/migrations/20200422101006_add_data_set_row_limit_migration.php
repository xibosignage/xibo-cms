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

class AddDataSetRowLimitMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // add the CMS Setting for hard limit on DataSet size.
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DATASET_HARD_ROW_LIMIT\'')) {

            $this->table('setting')->insert([
                [
                    'setting' => 'DATASET_HARD_ROW_LIMIT',
                    'value' => 10000,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }

        // add two new columns to DataSet table, soft limit on DataSet size and policy on what to do when the limit is hit (stop|fifo)
        $dataSetTable = $this->table('dataset');

        if (!$dataSetTable->hasColumn('rowLimit')) {
            $dataSetTable
                ->addColumn('rowLimit', 'integer', ['null' => true, 'default' => null])
                ->addColumn('limitPolicy', 'string', ['limit' => 50, 'default' => null, 'null' => true])
                ->save();
        }
    }
}
