<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Class CreateStatTableMigration
 */
class CreateStatTableMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // If stat table exists then rename it
        if ($this->hasTable('stat')) {
            $this->table('stat')->rename('stat_archive')->save();
        }

        // Create stat table
        $table = $this->table('stat', ['id' => 'statId']);
        $table

            ->addColumn('type', 'string', ['limit' => 20])
            ->addColumn('statDate', 'integer')
            ->addColumn('scheduleId', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('campaignId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('layoutId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('mediaId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('widgetId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('start', 'integer')
            ->addColumn('end', 'integer')
            ->addColumn('tag', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('duration', 'integer')
            ->addColumn('count', 'integer')

            ->addIndex('statDate')
            ->addIndex(['displayId', 'end', 'type'])
            ->save();


    }
}