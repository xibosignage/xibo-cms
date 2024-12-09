<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

use Phinx\Migration\AbstractMigration;

class OldUpgradeStep121Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 121;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $display = $this->table('display');
                $display
                    ->addColumn('xmrChannel', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('xmrPubKey', 'text', ['null' => true])
                    ->addColumn('lastCommandSuccess', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 2])
                    ->save();

                $settings = $this->table('setting');
                $settings
                    ->insert([
                        [
                            'setting' => 'XMR_ADDRESS',
                            'title' => 'XMR Private Address',
                            'helptext' => 'Please enter the private address for XMR.',
                            'value' => 'http:://localhost:8081',
                            'fieldType' => 'checkbox',
                            'options' => '',
                            'cat' => 'displays',
                            'userChange' => '1',
                            'type' => 'string',
                            'validation' => '',
                            'ordering' => '5',
                            'default' => 'http:://localhost:8081',
                            'userSee' => '1',
                        ],
                        [
                            'setting' => 'XMR_PUB_ADDRESS',
                            'title' => 'XMR Public Address',
                            'helptext' => 'Please enter the public address for XMR.',
                            'value' => 'tcp:://localhost:5556',
                            'fieldType' => 'dropdown',
                            'options' => 'Checked|Unchecked',
                            'cat' => 'displays',
                            'userChange' => '1',
                            'type' => 'string',
                            'validation' => '',
                            'ordering' => '6',
                            'default' => 'tcp:://localhost:5556',
                            'userSee' => '1',
                        ]
                    ])
                    ->save();

                $linkLayoutDisplayGroup = $this->table('lklayoutdisplaygroup', ['comment' => 'Layout associations directly to Display Groups']);
                $linkLayoutDisplayGroup->addColumn('layoutId', 'integer')
                    ->addColumn('displayGroupId', 'integer')
                    ->addIndex(['layoutId', 'displayGroupId'], ['unique' => true])
                    ->save();

                $pages = $this->table('pages');
                $pages->insert([
                    'name' => 'command',
                    'title' => 'Commands',
                    'asHome' => 1
                ])->save();

                $command = $this->table('command', ['id' => 'commandId']);
                $command->addColumn('command', 'string', ['limit' => 254])
                    ->addColumn('code', 'string', ['limit' => 50])
                    ->addColumn('description', 'string', ['limit' => 1000, 'null' => true])
                    ->addColumn('userId', 'integer')
                    ->save();

                $linkCommandDisplayProfile = $this->table('lkcommanddisplayprofile', ['id' => false, 'primary_key' => ['commandId', 'displayProfileId']]);
                $linkCommandDisplayProfile->addColumn('commandId', 'integer')
                    ->addColumn('displayProfileId', 'integer')
                    ->addColumn('commandString', 'string', ['limit' => 1000])
                    ->addColumn('validationString', 'string', ['limit' => 1000])
                    ->save();

                $schedule = $this->table('schedule');
                $schedule->changeColumn('campaignId', 'integer', ['null' => true])
                    ->addColumn('eventTypeId', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'after' => 'eventId', 'default' => 1])
                    ->addColumn('commandId', 'integer', ['after' => 'campaignId'])
                    ->changeColumn('toDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->save();

                $this->execute('UPDATE `schedule` SET `eventTypeId` = 1;');

                $scheduleDetail = $this->table('schedule_detail');
                $scheduleDetail->changeColumn('toDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->save();

                $media = $this->table('media');
                $media->addColumn('released', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
                    ->addColumn('apiRef', 'string', ['limit' => 254, 'null' => true])
                    ->save();

                $user = $this->table('user');
                $user->addColumn('firstName', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('lastName', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('phone', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('ref1', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('ref2', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('ref3', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('ref4', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('ref5', 'string', ['limit' => 254, 'null' => true])
                    ->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
