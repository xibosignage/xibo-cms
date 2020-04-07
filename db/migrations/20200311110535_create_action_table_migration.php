<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

class CreateActionTableMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Create table
        $table = $this->table('action', ['id' => 'actionId']);
        $table
            ->addColumn('ownerId', 'integer')
            ->addColumn('triggerType', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('triggerCode', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('actionType', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('source', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('sourceId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('target', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('targetId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('widgetId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('layoutCode', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addForeignKey('ownerId', 'user', 'userId')
            ->save();
    }
}
