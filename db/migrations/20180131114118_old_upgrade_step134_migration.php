<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep134Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 134;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('INSERT INTO displayprofile (name, type, config, isdefault, userId) VALUES (\'webOS\', \'lg\', \'{}\', 1, 1)');
                $this->execute('INSERT INTO module (Module, Name, Enabled, RegionSpecific, Description, ImageUri, SchemaVersion, ValidExtensions, PreviewEnabled, assignable, render_as, settings, viewPath, class, defaultDuration) VALUES (\'notificationview\', \'Notification\', 1, 1, \'Display Notifications from the Notification Centre\', \'forms/library.gif\', 1, null, 1, 1, \'html\', null, \'../modules\', \'Xibo\\\\Widget\\\\NotificationView\', 10);');

                $group = $this->table('group');
                $group
                    ->addColumn('isDisplayNotification', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                    ->save();

                $this->execute('DELETE FROM `setting` WHERE setting = \'MAINTENANCE_ALERTS_FOR_VIEW_USERS\';');

                $linkTagDisplayGroup = $this->table('lktagdisplaygroup', ['id' => 'lkTagDisplayGroupId']);
                $linkTagDisplayGroup
                    ->addColumn('tagId', 'integer')
                    ->addColumn('displayGroupId', 'integer')
                    ->addIndex(['tagId', 'displayGroupId'], ['unique' => true])
                    ->save();

                $media = $this->table('media');
                $media
                    ->addColumn('createdDt', 'datetime')
                    ->addColumn('modifiedDt', 'datetime')
                    ->save();

                $this->execute('UPDATE `module` SET validextensions = CONCAT(validextensions, \',ipk\') WHERE module = \'genericfile\' LIMIT 1;');

                $this->execute('UPDATE `module` SET description = \'A module for showing Currency pairs and exchange rates\' WHERE module = \'currencies\';');

                $this->execute('UPDATE `module` SET description = \'A module for showing Stock quotes\' WHERE module = \'stocks\';');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
