<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep124Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 124;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $group = $this->table('group');
                $group->addColumn('isSystemNotification', 'integer', ['default' => 0])
                    ->insert([
                        'group' => 'System Notifications',
                        'isUserSpecific' => 0,
                        'isSystemNotification' => 1
                    ])
                    ->save();

                $notification = $this->table('notification', ['id' => 'notificationId']);
                $notification
                    ->addColumn('subject', 'string', ['limit' => 255])
                    ->addColumn('body', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
                    ->addColumn('createDt', 'integer')
                    ->addColumn('releaseDt', 'integer')
                    ->addColumn('isEmail', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('isInterrupt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('isSystem', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('userId', 'integer')
                    ->save();

                $linkNotificationDg = $this->table('lknotificationdg', ['id' => 'lkNotificationDgId']);
                $linkNotificationDg
                    ->addColumn('notificationId', 'integer')
                    ->addColumn('displayGroupId', 'integer')
                    ->addIndex(['notificationId', 'displayGroupId'], ['unique' => true])
                    ->save();

                $linkNotificationGroup = $this->table('lknotificationgroup', ['id' => 'lkNotificationGroupId']);
                $linkNotificationGroup
                    ->addColumn('notificationId', 'integer')
                    ->addColumn('groupId', 'integer')
                    ->addIndex(['notificationId', 'groupId'], ['unique' => true])
                    ->save();

                $linkNotificationUser = $this->table('lknotificationuser', ['id' => 'lkNotificationUserId']);
                $linkNotificationUser
                    ->addColumn('notificationId', 'integer')
                    ->addColumn('userId', 'integer')
                    ->addColumn('read', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('readDt', 'integer')
                    ->addColumn('emailDt', 'integer')
                    ->addIndex(['notificationId', 'userId'], ['unique' => true])
                    ->save();

                $pages = $this->table('pages');
                $pages->insert([
                    [
                        'name' => 'notification',
                        'title' => 'Notifications',
                        'asHome' => 0
                    ],
                    [
                        'name' => 'drawer',
                        'title' => 'Notification Drawer',
                        'asHome' => 0
                    ]
                ])->save();

                $permissionEntity = $this->table('permissionentity');
                $permissionEntity->insert([
                    'entity' => '\\Xibo\\Entity\\Notification'
                ])->save();

                $this->execute('UPDATE `group` SET isSystemNotification = 1 WHERE isUserSpecific = 1 AND `groupId` IN (SELECT `groupId` FROM `lkusergroup` INNER JOIN `user` ON `user`.userId = `lkusergroup`.userId WHERE `user`.userTypeId = 1);');

                // If we've run step 92 as part of this upgrade, then don't do the below
                $this->execute('ALTER TABLE  `datasetcolumn` CHANGE  `ListContent`  `ListContent` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');

                if (!$this->checkIndexExists('stat', ['displayId', 'end', 'type'], false)) {
                    $this->execute('ALTER TABLE `stat` ADD INDEX Type (`displayID`, `end`, `Type`);');
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }

    /**
     * Check if an index exists
     * @param string $table
     * @param string[] $columns
     * @param bool $isUnique
     * @return bool
     * @throws InvalidArgumentException
     */
    private function checkIndexExists($table, $columns, $isUnique)
    {
        if (!is_array($columns) || count($columns) <= 0)
            throw new InvalidArgumentException('Incorrect call to checkIndexExists', 'columns');

        // Use the information schema to see if the index exists or not.
        // all users have permission to the information schema
        $sql = '
          SELECT * 
            FROM INFORMATION_SCHEMA.STATISTICS 
           WHERE table_schema=DATABASE() 
            AND table_name = \'' . $table . '\'
            AND non_unique = \'' . (($isUnique) ? 0 : 1) . '\'
            AND (
        ';

        $i = 0;
        foreach ($columns as $column) {
            $i++;

            $sql .= (($i == 1) ? '' : ' OR') . ' (seq_in_index = \'' . $i . '\' AND column_name = \'' . $column . '\') ';
        }

        $sql .= ' )';

        $indexes = $this->fetchAll($sql);

        return (count($indexes) === count($columns));
    }
}
