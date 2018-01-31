<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep133Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 133;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES (\'DISPLAY_LOCK_NAME_TO_DEVICENAME\', \'0\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Lock the Display Name to the device name provided by the Player?\', \'\', 80, \'0\', 1, \'checkbox\');');

                $task = $this->table('task');
                $task
                    ->addColumn('lastRunStartDt', 'integer', ['null' => true])
                    ->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
