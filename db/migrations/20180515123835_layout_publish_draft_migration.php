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
 * Class LayoutPublishDraftMigration
 */
class LayoutPublishDraftMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Add a status table
        $status = $this->table('status');
        $status
            ->addColumn('status', 'string', ['limit' => 254])
            ->save();

        // We must ensure that the IDs are added as we expect (don't rely on auto_increment)
        $this->execute('INSERT INTO `status` (`id`, `status`) VALUES (1, \'Published\'), (2, \'Draft\'), (3, \'Pending Approval\')');

        $this->execute('UPDATE `layout` SET createdDt = \'1970-01-01 00:00:00\' WHERE createdDt < \'2000-01-01\'');
        $this->execute('UPDATE `layout` SET modifiedDt = \'1970-01-01 00:00:00\' WHERE modifiedDt < \'2000-01-01\'');

        // Add a reference to the Layout and Playlist tables for "parentId"
        $layout = $this->table('layout');
        $layout
            ->changeColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->changeColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('parentId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('publishedStatusId', 'integer', ['default' => 1])
            ->addForeignKey('publishedStatusId', 'status')
            ->save();
    }
}
