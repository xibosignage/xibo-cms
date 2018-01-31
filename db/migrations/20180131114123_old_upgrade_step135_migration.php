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
                $dataSet = $this->table('dataSet');
                $dataSet
                    ->addColumn('isRemote', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                    ->addColumn('method', 'enum', ['limit' => ['GET', 'POST'], 'null' => true])
                    ->addColumn('uri', 'string', ['limit' => 250, 'null' => true])
                    ->addColumn('postData', 'text', ['null' => true])
                    ->addColumn('authentication', 'enum', ['limit' => ['none', 'plain', 'basic', 'digest'], 'null' => true])
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

                $dataSetColumn = $this->table('datasetcolumntype');
                $dataSetColumn
                    ->addColumn('remoteField', 'string', ['limit' => 250, 'null' => true, 'after' => 'formula'])
                    ->save();

                $task = $this->table('task');
                $task->insert([
                    'name' => 'Fetch Remote DataSets',
                    'class' => '\\Xibo\\XTR\\RemoteDataSetFetchTask\\',
                    'options' => '[]',
                    'schedule' => '30 * * * * *',
                    'isActive' => '1',
                    'configFile' => '/tasks/remote-dataset.task'
                ])->save();

                // Remove the version table
                $this->dropTable('upgrade');
                $this->dropTable('version');
            }
        }
    }
}
