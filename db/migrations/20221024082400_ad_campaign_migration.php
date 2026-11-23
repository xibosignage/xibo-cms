<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
        // Schedule table gets some new fields.
        // one to set the maximum number of plays per hour
        // the other to indicate if the schedule is part of a parent campaign
        $this->table('schedule')
            ->addColumn('maxPlaysPerHour', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL,
                'default' => 0,
            ])
            ->addColumn('parentCampaignId', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'null' => true,
                'default' => null,
            ])
            ->save();

        // More information on each campaign.
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
                'length' => 6,
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
            ->addColumn('spend', 'decimal', [
                'precision' => 30,
                'scale' => 4,
                'default' => 0,
            ])
            ->addColumn('impressions', 'decimal', [
                'precision' => 30,
                'scale' => 4,
                'default' => 0,
            ])
            ->addColumn('lastPopId', 'string', [
                'length' => 50,
                'default' => null,
                'null' => true,
            ])
            ->addColumn('listPlayOrder', 'string', [
                'length' => 6,
                'default' => 'round',
                'null' => false,
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

        // Direct links between the campaign and its target displays/groups
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

        // Links between the campaign and the layout are extended to cover scheduling and geo fences.
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

        // Add a task for keeping ad campaigns up to date
        $this->table('task')
            ->insert([
                'name' => 'Campaign Scheduler',
                'class' => '\Xibo\XTR\CampaignSchedulerTask',
                'options' => '[]',
                'schedule' => '45 * * * *',
                'isActive' => '1',
                'configFile' => '/tasks/campaign-scheduler.task',
                'pid' => 0,
                'lastRunDt' => 0,
                'lastRunDuration' => 0,
                'lastRunExitCode' => 0
            ])
            ->save();

        // Add parentCampaignId to the stats table.
        $this->table('stat')
            ->addColumn('parentCampaignId', 'integer', [
                'default' => 0,
                'null' => false,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
            ])
            ->save();
    }
}
