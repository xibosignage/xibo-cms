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
 * Migration for adding more columns to displayevent table.
 * Add a new column on Command table for createAlertOn.
 * Add a new column on lkcommanddisplayprofile for createAlertOn.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class DisplayAlertsMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('displayevent')
            ->changeColumn('start', 'integer', ['null' => true])
            ->addColumn('eventTypeId', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('refId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('detail', 'text', ['null' => true, 'default' => null])
            ->save();

        $this->table('command')
            ->addColumn('createAlertOn', 'string', ['null' => false, 'default' => 'never'])
            ->save();

        $this->table('lkcommanddisplayprofile')
            ->addColumn('createAlertOn', 'string', ['null' => true, 'default' => null])
            ->save();
    }
}
