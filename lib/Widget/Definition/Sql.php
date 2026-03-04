<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

namespace Xibo\Widget\Definition;

/**
 * SQL definitions
 */
class Sql
{
    const DISALLOWED_KEYWORDS = [
        ';',
        'INSERT',
        'UPDATE',
        'SELECT',
        'FROM',
        'WHERE',
        'DELETE',
        'TRUNCATE',
        'TABLE',
        'ALTER',
        'GRANT',
        'REVOKE',
        'CREATE',
        'DROP',
        'UNION',
        'HAVING',
        'GROUP',
        'INTO',
        'OUTFILE',
        'DUMPFILE',
        'PROCEDURE',
        'SLEEP',
        'BENCHMARK',
        '--',
        '#',
        '/*',
        '*/',
        'INFORMATION_SCHEMA',
        'LOAD_FILE',
        'LOCK',
        'EXECUTE',
        'PREPARE',
        'DEALLOCATE',
        'SHOW',
        'DESCRIBE',
        'EXPLAIN',
        'CALL',
        'HANDLER',
        'RENAME',
        'SHUTDOWN',
        'SET',
        'USE',
        'FLUSH',
        'KILL',
        'OPTIMIZE',
        'REPAIR',
        'ANALYZE',
        'CHECK',
        'CHECKSUM',
    ];

    /**
     * Cleanup SQL
     * @param string $sql the SQL to clean
     * @param int $total the total number of replacements
     * @return string
     */
    public static function cleanup(string $sql, int &$total = 0): string
    {
        $count = 0;
        do {
            $sql = str_ireplace(self::DISALLOWED_KEYWORDS, '', $sql, $count);
            $total += $count;
        } while ($count > 0);

        return $sql;
    }
}
