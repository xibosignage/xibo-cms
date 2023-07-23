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
