<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep92Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 92;

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

                $this->execute('ALTER TABLE  `datasetcolumn` CHANGE  `ListContent`  `ListContent` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');

                $this->execute('ALTER TABLE `stat` ADD INDEX Type (`displayID`, `end`, `Type`);');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
