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
 * Add the new display_media which will represent the link between module
 * files and the displays they should be served to
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class DisplayMediaMigration extends AbstractMigration
{
    public function change()
    {
        // Link Media to Display
        $table = $this->table('display_media');
        $table
            ->addColumn('displayId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addColumn('modifiedAt', 'timestamp', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP'
            ])
            ->addIndex(['displayId', 'mediaId'], ['unique' => true])
            ->addForeignKey('displayId', 'display', 'displayId')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->save();
    }
}
