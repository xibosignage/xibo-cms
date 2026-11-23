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
 * Migrations for schedule criteria
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class ScheduleCriteriaMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('schedule_criteria')
            ->addColumn('eventId', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'null' => false,
            ])
            ->addColumn('type', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('metric', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('condition', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('value', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addForeignKey('eventId', 'schedule', 'eventId')
            ->save();
    }
}
