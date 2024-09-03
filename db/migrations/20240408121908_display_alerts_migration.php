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
