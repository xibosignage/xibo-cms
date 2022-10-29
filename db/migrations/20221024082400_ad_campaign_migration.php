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
 * Ad Campaigns
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AdCampaignMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Add a new field for the home folder
        $this->table('schedule')
            ->addColumn('maxPlaysPerHour', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL,
                'default' => 0
            ])
            ->save();

        $this->table('campaign')
            ->addColumn('type', 'string', [
                'default' => 'list',
                'null' => false,
                'limit' => 10
            ])
            ->addColumn('startDt', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('endDt', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('targetType', 'string', [
                'length' => '6',
                'null' => true,
                'default' => null,
            ])
            ->addColumn('target', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('plays', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'default' => 0,
            ])
            ->addColumn('spend', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'default' => 0,
            ])
            ->addColumn('impressions', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'default' => 0,
            ])
            ->addColumn('lastPopId', 'string', [
                'length' => 50,
                'default' => null,
                'null' => true,
            ])
            ->addColumn('ref1', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 254
            ])
            ->addColumn('ref2', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 254
            ])
            ->addColumn('ref3', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 254
            ])
            ->addColumn('ref4', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 254
            ])
            ->addColumn('ref5', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 254
            ])
            ->addColumn('createdAt', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => ''
            ])
            ->addColumn('modifiedAt', 'timestamp', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('modifiedBy', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'default' => 0,
            ])
            ->save();

        $this->table('lkcampaigndisplaygroup', [
            'id' => false,
            'primary_key' => ['campaignId', 'displayGroupId']
        ])
            ->addColumn('campaignId', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('displayGroupId', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
            ])
            ->addForeignKey('campaignId', 'campaign', 'campaignId')
            ->addForeignKey('displayGroupId', 'displaygroup', 'displayGroupId')
            ->save();

        $this->table('lkcampaignlayout')
            ->addColumn('dayPartId', 'integer', [
                'default' => null,
                'null' => true,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('daysOfWeek', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 50,
            ])
            ->addColumn('geoFence', 'text', [
                'default' => null,
                'null' => true,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
            ])
            ->save();
    }
}
