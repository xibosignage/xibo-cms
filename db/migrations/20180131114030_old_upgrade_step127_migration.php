<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep127Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 127;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $schedule = $this->table('schedule');
                $schedule->addColumn('recurrenceRepeatsOn', 'string', ['null' => true])
                    ->save();

                $this->execute('INSERT INTO `setting` (`setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES (\'RESTING_LOG_LEVEL\', \'Error\', \'dropdown\', \'Set the level of the resting log level. The CMS will revert to this log level after an elevated period ends. In production systems \"error\" is recommended.\', \'Emergency|Alert|Critical|Error\', \'troubleshooting\', 1, \'Resting Log Level\', \'\', 19, \'error\', 1, \'word\');');

                $dataSet = $this->table('dataset');
                $dataSet->changeColumn('code', 'string', ['limit' => 50, 'null' => true])
                    ->save();

                $this->execute('INSERT INTO `pages` (`name`, `Title`, `asHome`) VALUES (\'daypart\', \'Dayparting\', 0);');

                $dayPart = $this->table('daypart', ['id' => 'dayPartId']);
                $dayPart
                    ->addColumn('name', 'string', ['limit' => 50])
                    ->addColumn('description', 'string', ['limit' => 50, 'null' => true])
                    ->addColumn('isRetired', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('userId', 'integer')
                    ->addColumn('startTime', 'string', ['limit' => 8, 'default' => '00:00:00'])
                    ->addColumn('endTime', 'string', ['limit' => 8, 'default' => '00:00:00'])
                    ->addColumn('exceptions', 'text')
                    ->save();

                $this->execute('INSERT INTO `permissionentity` (`entityId`, `entity`) VALUES (NULL, \'Xibo\\Entity\\DayPart\');');

                $user = $this->table('user');
                $user->changeColumn('userPassword', 'string', ['limit' => 255]);

                $this->execute('INSERT INTO pages (name, title, asHome) VALUES (\'task\', \'Task\', 1);');

                $this->execute('INSERT INTO setting (setting, value, fieldType, helptext, options, cat, userChange, title, validation, ordering, `default`, userSee, type) VALUES (\'TASK_CONFIG_LOCKED_CHECKB\', \'Unchecked\', \'dropdown\', \'Is the task config locked? Useful for Service providers.\', \'Checked|Unchecked\', \'defaults\', 0, \'Lock Task Config\', \'\', 30, \'Unchecked\', 0, \'word\');');

                $task = $this->table('task', ['id' => 'taskId']);
                $task
                    ->addColumn('name', 'string', ['limit' => 254])
                    ->addColumn('class', 'string', ['limit' => 254])
                    ->addColumn('status', 'integer', ['default' => 2, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('pid', 'integer')
                    ->addColumn('options', 'text')
                    ->addColumn('schedule', 'string', ['limit' => 254])
                    ->addColumn('lastRunDt', 'integer')
                    ->addColumn('lastRunMessage', 'string', ['null' => true])
                    ->addColumn('lastRunStatus', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('lastRunDuration', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
                    ->addColumn('lastRunExitCode', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
                    ->addColumn('isActive', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('runNow', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('configFile', 'string', ['limit' => 254])
                    ->insert([
                        [
                            'name' => 'Daily Maintenance',
                            'class' => '\Xibo\XTR\MaintenanceDailyTask',
                            'options' => '[]',
                            'schedule' => '0 0 * * * *',
                            'isActive' => '1',
                            'configFile' => '/tasks/maintenance-daily.task'
                        ],
                        [
                            'name' => 'Regular Maintenance',
                            'class' => '\Xibo\XTR\MaintenanceRegularTask',
                            'options' => '[]',
                            'schedule' => '*/5 * * * * *',
                            'isActive' => '1',
                            'configFile' => '/tasks/maintenance-regular.task'
                        ],
                        [
                            'name' => 'Email Notifications',
                            'class' => '\Xibo\XTR\EmailNotificationsTask',
                            'options' => '[]',
                            'schedule' => '*/5 * * * * *',
                            'isActive' => '1',
                            'configFile' => '/tasks/email-notifications.task'
                        ],
                        [
                            'name' => 'Stats Archive',
                            'class' => '\Xibo\XTR\StatsArchiveTask',
                            'options' => '{"periodSizeInDays":"7","maxPeriods":"4"}',
                            'schedule' => '0 0 * * Mon',
                            'isActive' => '1',
                            'configFile' => '/tasks/stats-archiver.task'
                        ],
                        [
                            'name' => 'Remove old Notifications',
                            'class' => '\Xibo\XTR\NotificationTidyTask',
                            'options' => '{"maxAgeDays":"7","systemOnly":"1","readOnly":"0"}',
                            'schedule' => '15 0 * * *',
                            'isActive' => '1',
                            'configFile' => '/tasks/notification-tidy.task'
                        ]
                    ])
                    ->save();

                $this->execute('INSERT INTO `setting` (setting, value, fieldType, helptext, options, cat, userChange, title, validation, ordering, `default`, userSee, type) VALUES(\'WHITELIST_LOAD_BALANCERS\', \'\', \'text\', \'If the CMS is behind a load balancer, what are the load balancer IP addresses, comma delimited.\', \'\', \'network\', 1, \'Whitelist Load Balancers\', \'\', 100, \'\', 1, \'string\');');

                $this->execute('INSERT INTO `setting` (setting, value, fieldType, helptext, options, cat, userChange, title, validation, ordering, `default`, userSee, type) VALUES(\'DEFAULT_LAYOUT\', \'1\', \'text\', \'The default layout to assign for new displays and displays which have their current default deleted.\', \'1\', \'displays\', 1, \'Default Layout\', \'\', 4, \'\', 1, \'int\');');

                $display = $this->table('display');
                $display->addColumn('deviceName', 'string', ['null' => true])
                    ->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
