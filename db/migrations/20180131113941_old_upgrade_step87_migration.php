<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep87Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 87;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $settings = $this->table('setting');
                $settings
                    ->insert([
                        'setting' => 'PROXY_EXCEPTIONS',
                        'title' => 'Proxy Exceptions',
                        'helptext' => 'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.',
                        'value' => '1',
                        'fieldType' => 'text',
                        'options' => '',
                        'cat' => 'network',
                        'userChange' => '1',
                        'type' => 'string',
                        'validation' => '',
                        'ordering' => '32',
                        'default' => '',
                        'userSee' => '1',
                    ])
                    ->save();

                // If we haven't run step85 during this migration, then we will want to update our storageAvailable columns
                // Change to big ints.
                $display = $this->table('display');
                $display
                    ->changeColumn('storageAvailableSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->changeColumn('storageTotalSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
