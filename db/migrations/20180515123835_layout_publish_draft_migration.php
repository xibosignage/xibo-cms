<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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

        // Add a reference to the Layout and Playlist tables for "parentId"
        $layout = $this->table('layout');
        $layout
            ->addColumn('parentId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('publishedStatusId', 'integer', ['default' => 1])
            ->addForeignKey('publishedStatusId', 'status')
            ->save();
    }
}
