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

class RemoteDataSetCsvSourceMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $dataSetTable = $this->table('dataset');

        // Add new columns to dataSet table - ignoreFirstRow and sourceId
        if (!$dataSetTable->hasColumn('sourceId')) {
            $dataSetTable
                ->addColumn('ignoreFirstRow', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => null, 'null' => true])
                ->addColumn('sourceId', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => null, 'null' => true])
                ->save();
        }

        // get all existing remote dataSets
        $getRemoteDataSetsQuery = $this->query('SELECT dataSetId, dataSet FROM dataset WHERE isRemote = 1');
        $getRemoteDataSetsResults = $getRemoteDataSetsQuery->fetchAll(PDO::FETCH_ASSOC);

        // set the sourceId to 1 (json) on all existing remote dataSets
        foreach ($getRemoteDataSetsResults as $dataSetsResult) {
            $this->execute('UPDATE dataset SET sourceId = 1 WHERE dataSetId = ' . $dataSetsResult['dataSetId']);
        }
    }
}
