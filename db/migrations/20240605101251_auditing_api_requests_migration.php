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
 * Migration to Add a new table for session_history
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AuditingApiRequestsMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('application_requests_history', ['id' => 'requestId'])
            ->addColumn('userId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('applicationId', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('url', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('method', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addColumn('startTime', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('endTime', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('duration', 'integer', ['null' => true, 'default' => null])
            ->addIndex('userId')
            ->create();

        $this->table('auditlog')
            ->addColumn('requestId', 'integer', ['null' => true, 'default' => null])
            ->save();

        $this->table('log')
            ->addColumn('requestId', 'integer', ['null' => true, 'default' => null])
            ->save();
    }
}
