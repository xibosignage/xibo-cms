<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep131Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 131;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES    (\'DISPLAY_PROFILE_STATS_DEFAULT\', \'0\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Default setting for Statistics Enabled?\', \'\', 70, \'0\', 1, \'checkbox\'),(\'DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Enable the option to report the current layout status?\', \'\', 80, \'0\', 1, \'checkbox\'),(\'DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Enable the option to set the screenshot interval?\', \'\', 90, \'0\', 1, \'checkbox\'),(\'DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT\', \'200\', \'number\', \'The default size in pixels for the Display Screenshots\', NULL, \'displays\', 1, \'Display Screenshot Default Size\', \'\', 100, \'200\', 1, \'int\'),(\'LATEST_NEWS_URL\', \'http://xibo.org.uk/feed\', \'text\', \'RSS/Atom Feed to be displayed on the Status Dashboard\', \'\', \'general\', 0, \'Latest News URL\', \'\', 111, \'\', 0, \'string\');');

                $display = $this->table('display');
                $display->removeColumn('currentLayoutId')->save();

                $permissionEntity = $this->table('permissionentity');
                $permissionEntity->insert([
                    'entity' => 'Xibo\\Entity\\Display'
                ])->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
