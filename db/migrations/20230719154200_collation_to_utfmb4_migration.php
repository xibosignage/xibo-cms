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
 * Convert all tables in the database to UF8MB4
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class CollationToUtfmb4Migration extends AbstractMigration
{
    public function change(): void
    {
        // Several tables have keys on varchar which will need to be resized to accomodate the max key length
        // of MySQL 5.6 which is 767 bytes. This will mean 191.
        $this->table('menu_product_options')->changeColumn('option', 'string', [
            'limit' => 191,
        ])->save();

        $this->table('widgetoption')->changeColumn('option', 'string', [
            'limit' => 191,
        ])->save();

        // Get all tables which need to have their collation converted.
        // We will exclude some tables which have foreign keys on string columns.
        $tables = $this->fetchAll('
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_TYPE` = \'BASE TABLE\'
                AND `ENGINE` = \'InnoDB\'
                AND TABLE_COLLATION <> \'utf8mb4_general_ci\'
                AND `TABLE_NAME` NOT IN (
                    \'oauth_clients\',
                    \'oauth_scopes\',
                    \'oauth_client_scopes\',
                    \'oauth_lkclientuser\'
                )
        ');

        foreach ($tables as $row) {
            $this->execute('ALTER TABLE `' . $row['TABLE_NAME']
                . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    }
}
