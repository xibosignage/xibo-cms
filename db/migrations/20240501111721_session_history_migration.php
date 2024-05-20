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
 * Migration to Add a new table for session_history
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class SessionHistoryMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('session_history', ['id' => 'sessionId'])
            ->addColumn('ipAddress', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('userAgent', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('startTime', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('userId', 'integer', ['null' => true, 'default' => null])
            ->addIndex('userId')
            ->create();

        $this->table('auditlog')
            ->addColumn('sessionHistoryId', 'integer', ['null' => true, 'default' => null])
            ->save();

        $this->table('log')
            ->addColumn('sessionHistoryId', 'integer', ['null' => true, 'default' => null])
            ->save();
    }
}
