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
 * Adds library_file_journal, which records library file writes and deletes so failures never orphan a file.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddLibraryFileJournalMigration extends AbstractMigration
{
    public function up(): void
    {
        $this->table('library_file_journal')
            ->addColumn('storageKey', 'string', ['limit' => 1000])
            ->addColumn('assetType', 'string', ['limit' => 50])
            ->addColumn('location', 'string', ['limit' => 255])
            ->addColumn('state', 'string', ['limit' => 20])
            ->addColumn('createdDt', 'integer')
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('lastError', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addIndex(['state', 'assetType', 'location', 'createdDt'])
            ->create();
    }

    public function down(): void
    {
        $this->table('library_file_journal')->drop()->save();
    }
}
