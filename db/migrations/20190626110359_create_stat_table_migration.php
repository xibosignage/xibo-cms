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