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
 * Adds the md5 and size of each Layout's stored XLF, filled in when it's next built or first read.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddLayoutXlfDetailsMigration extends AbstractMigration
{
    public function up(): void
    {
        $this->table('layout')
            ->addColumn('xlfMd5', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('xlfSize', 'biginteger', ['null' => true, 'default' => null])
            ->save();
    }

    public function down(): void
    {
        $this->table('layout')
            ->removeColumn('xlfMd5')
            ->removeColumn('xlfSize')
            ->save();
    }
}
