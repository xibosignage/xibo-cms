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
 * Adds storageEngine to the tables whose files are copied in the background: null for a file in the
 * configured store, 'local' for one still waiting to be copied, or the engine it was copied to. Also indexes
 * media.storedAs for the library file reference checks.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddStorageEngineMigration extends AbstractMigration
{
    public function up(): void
    {
        foreach (['media', 'saved_report'] as $table) {
            $this->table($table)
                ->addColumn('storageEngine', 'string', ['limit' => 20, 'null' => true, 'default' => null])
                ->save();
        }

        // Before a library file is deleted, after commit or on a tidy retry, LibraryFileReferences checks no media row
        // still uses its key (WHERE storedAs = ?); without this index each check scans the whole media table.
        $this->table('media')
            ->addIndex(['storedAs'], ['name' => 'idx_media_storedAs'])
            ->save();
    }

    public function down(): void
    {
        // Dropping the column would lose which files are still local.
        if ($this->fetchRow('SELECT 1 FROM `media` WHERE `storageEngine` = \'local\'
            UNION ALL SELECT 1 FROM `saved_report` WHERE `storageEngine` = \'local\' LIMIT 1')) {
            throw new \RuntimeException('Some files have storageEngine \'local\', so the column cannot be removed.');
        }

        // An early version of this migration didn't add the index.
        $media = $this->table('media');
        if ($media->hasIndexByName('idx_media_storedAs')) {
            $media->removeIndexByName('idx_media_storedAs')->save();
        }

        foreach (['media', 'saved_report'] as $table) {
            $this->table($table)
                ->removeColumn('storageEngine')
                ->save();
        }
    }
}
