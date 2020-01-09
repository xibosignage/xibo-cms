<?php


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
