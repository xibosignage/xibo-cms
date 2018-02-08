<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep135Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 135;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $dataSet = $this->table('dataset');
                $dataSet
                    ->addColumn('isRemote', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                    ->addColumn('method', 'enum', ['values' => ['GET', 'POST'], 'null' => true])
                    ->addColumn('uri', 'string', ['limit' => 250, 'null' => true])
                    ->addColumn('postData', 'text', ['null' => true])
                    ->addColumn('authentication', 'enum', ['values' => ['none', 'plain', 'basic', 'digest'], 'null' => true])
                    ->addColumn('username', 'string', ['limit' => 250, 'null' => true])
                    ->addColumn('password', 'string', ['limit' => 250, 'null' => true])
                    ->addColumn('refreshRate', 'integer', ['default' => 86400])
                    ->addColumn('clearRate', 'integer', ['default' => 0])
                    ->addColumn('runsAfter', 'integer', ['default' => null, 'null' => true])
                    ->addColumn('dataRoot', 'string', ['limit' => 250, 'null' => true])
                    ->addColumn('lastSync', 'integer', ['default' => 0])
                    ->addColumn('summarize', 'string', ['limit' => 10, 'null' => true])
                    ->addColumn('summarizeField', 'string', ['limit' => 250, 'null' => true])
                    ->save();

                $dataSetColumn = $this->table('datasetcolumn');
                $dataSetColumn
                    ->addColumn('remoteField', 'string', ['limit' => 250, 'null' => true, 'after' => 'formula'])
                    ->save();

                $task = $this->table('task');
                $task->insert([
                    [
                        'name' => 'Fetch Remote DataSets',
                        'class' => '\Xibo\XTR\RemoteDataSetFetchTask',
                        'options' => '[]',
                        'schedule' => '30 * * * * *',
                        'isActive' => '1',
                        'configFile' => '/tasks/remote-dataset.task'
                    ],
                    [
                        'name' => 'Update Empty Video Durations',
                        'class' => '\Xibo\XTR\UpdateEmptyVideoDurations',
                        'options' => '[]',
                        'schedule' => '0 0 1 1 *',
                        'isActive' => '1',
                        'configFile' => '/tasks/update-empty-video-durations.task',
                        'runNow' => 1
                    ],
                    [
                        'name' => 'Drop Player Cache',
                        'class' => '\Xibo\XTR\DropPlayerCacheTask',
                        'options' => '[]',
                        'schedule' => '0 0 1 1 *',
                        'isActive' => '1',
                        'configFile' => '/tasks/drop-player-cache.task',
                        'runNow' => 1
                    ],
                    [
                        'name' => 'DataSet Convert (only run once)',
                        'class' => '\Xibo\XTR\DataSetConvertTask',
                        'options' => '[]',
                        'schedule' => '0 0 1 1 *',
                        'isActive' => '1',
                        'configFile' => '/tasks/dataset-convert.task',
                        'runNow' => 1
                    ],
                    [
                        'name' => 'Layout Convert (only run once)',
                        'class' => '\Xibo\XTR\LayoutConvertTask',
                        'options' => '[]',
                        'schedule' => '0 0 1 1 *',
                        'isActive' => '1',
                        'configFile' => '/tasks/layout-convert.task',
                        'runNow' => 1
                    ],
                ])->save();

                // If we've run the old upgrader, remove it
                if ($this->hasTable('upgrade'))
                    $this->dropTable('upgrade');

                // Remove the version table
                $this->dropTable('version');
            }
        }
    }
}
