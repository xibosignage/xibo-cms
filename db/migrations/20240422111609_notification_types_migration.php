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

/**
 * Add new notification type column
 * Add new notification email options for user groups
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class NotificationTypesMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('notification')
            ->removeColumn('isEmail')
            ->addColumn('type', 'string', ['null' => false, 'default' => 'unknown', 'limit' => 50])
            ->save();

        $this->table('group')
            ->addColumn(
                'isDataSetNotification',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0]
            )
            ->addColumn(
                'isLayoutNotification',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0]
            )
            ->addColumn(
                'isLibraryNotification',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0]
            )
            ->addColumn(
                'isReportNotification',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0]
            )
            ->addColumn(
                'isScheduleNotification',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0]
            )
            ->addColumn(
                'isCustomNotification',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0]
            )
            ->save();
    }
}
