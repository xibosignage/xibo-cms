<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep123Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 123;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $schedule = $this->table('schedule');
                $schedule->addColumn('dayPartId', 'integer')
                    ->changeColumn('fromDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->save();

                // The following was added in step 92, we need to check to see if we already have this
                if (!$this->fetchRow('SELECT * FROM setting WHERE setting = \'CDN_URL\'')) {
                    $settings = $this->table('setting');
                    $settings
                        ->insert([
                            'setting' => 'CDN_URL',
                            'title' => 'CDN Address',
                            'helptext' => 'Content Delivery Network Address for serving file requests to Players',
                            'value' => '',
                            'fieldType' => 'text',
                            'options' => '',
                            'cat' => 'network',
                            'userChange' => '0',
                            'type' => 'string',
                            'validation' => '',
                            'ordering' => '33',
                            'default' => '',
                            'userSee' => '0',
                        ])
                        ->save();
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
