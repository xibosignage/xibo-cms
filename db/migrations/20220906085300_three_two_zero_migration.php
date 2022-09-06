<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

/**
 * Minor DB changes for 3.2.0
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class ThreeTwoZeroMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // New Ip Address field
        $this->table('display')
            ->addColumn('lanIpAddress', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => null,
            ])
            ->save();

        // Add the Dashboards connector, disabled.
        $this->table('connectors')
            ->insert([
                'className' => '\\Xibo\\Connector\\XiboDashboardConnector',
                'isEnabled' => 0,
                'isVisible' => 1
            ])
            ->save();

        // Dynamic criteria tags
        $this->table('displaygroup')
            ->changeColumn('dynamicCriteria', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->save();

        $this->table('playlist')
            ->changeColumn('filterMediaName', 'text', [
                'null' => true,
                'default' => null
            ])
            ->changeColumn('filterMediaTags', 'text', [
                'null' => true,
                'default' => null
            ])
            ->save();

        // Resolution on media
        $this->table('media')
            ->addColumn('width', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_MEDIUM,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('height', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_MEDIUM,
                'null' => true,
                'default' => null,
            ])
            ->save();

        // Setting for folders.
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'FOLDERS_ALLOW_SAVE_IN_ROOT\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'FOLDERS_ALLOW_SAVE_IN_ROOT',
                    'value' => '1',
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }
    }
}
