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
        ';', '@@', // Reduced symbols, handling comments via regex now
        'INSERT', 'UPDATE', 'SELECT', 'FROM', 'WHERE', 'DELETE', 'TRUNCATE',
        'TABLE', 'ALTER', 'GRANT', 'REVOKE', 'CREATE', 'DROP', 'UNION',
        'HAVING', 'GROUP', 'INTO', 'OUTFILE', 'DUMPFILE', 'PROCEDURE',
        'SLEEP', 'BENCHMARK', 'INFORMATION_SCHEMA', 'LOAD_FILE', 'LOCK',
        'EXECUTE', 'PREPARE', 'DEALLOCATE', 'SHOW', 'DESCRIBE', 'EXPLAIN',
        'CALL', 'HANDLER', 'RENAME', 'SHUTDOWN', 'SET', 'USE', 'FLUSH',
        'KILL', 'OPTIMIZE', 'REPAIR', 'ANALYZE', 'CHECK', 'CHECKSUM',
        'GET_LOCK', 'RELEASE_LOCK', 'IS_FREE_LOCK', 'IS_USED_LOCK',
        'MASTER_POS_WAIT', 'PASSWORD', 'USER', 'SYSTEM_USER', 'SESSION_USER',
        'CURRENT_USER', 'DATABASE', 'SCHEMA', 'VERSION', 'CONNECTION_ID',
        'LAST_INSERT_ID', 'ROW_COUNT', 'FOUND_ROWS', 'LOAD_XML', 'NAME_CONST',
        'DO', 'EXTRACTVALUE', 'UPDATEXML', 'XMLTYPE', 'DBMS_PIPE', 'PG_SLEEP',
        // Added String Builders & Encoders
        // we have specific use cases for 'CONCAT', so we keept that
        'CONCAT_WS', 'CHAR', 'UNHEX', 'HEX', 'ASCII', 'BIN', 'ORD', 'BASE64'
    ];

    /**
     * Cleanup SQL (Maximum Paranoia for Legacy Code)
     * @param string $sql the SQL to clean
     * @param int $total the total number of replacements
     * @return string
     */
    public static function cleanup(string $sql, int &$total = 0): string
    {
        // 1. EXTRACT AND PROTECT STRING LITERALS (Preserve user data)
        $strings = [];
        $placeholderPrefix = '__SQL_STR_';
        $stringPattern = '/(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")/';

        $sqlCleaned = preg_replace_callback($stringPattern, function ($matches) use (&$strings, $placeholderPrefix) {
            $id = count($strings);
            $strings[] = $matches[0];
            return $placeholderPrefix . $id . '__';
        }, $sql);

        // 2. STRIP COMMENTS & ENCODINGS (Before keyword checks)
        $preCleanupPatterns = [
            '/(?:\/\*.*?\*\/|--[ \t].*?(?:\n|$)|#[^\n]*?(?:\n|$))/s', // Standard & Executable Comments
            '/\b0x[0-9a-fA-F]+\b/',                                   // Hex literals (e.g., 0x7e)
            '/\bb\'[01]+\'/i'                                         // Binary literals
        ];
        $sqlCleaned = preg_replace($preCleanupPatterns, '', $sqlCleaned, -1, $preCleanupCount);
        $total += $preCleanupCount;

        // 3. PREPARE KEYWORD PATTERNS
        $wordKeywords = [];
        $symbolKeywords = [];

        foreach (self::DISALLOWED_KEYWORDS as $keyword) {
            if (ctype_alnum(str_replace('_', '', $keyword))) {
                $wordKeywords[] = preg_quote($keyword, '/');
            } else {
                $symbolKeywords[] = $keyword;
            }
        }

        $wordPattern = empty($wordKeywords) ? null : '/\b(' . implode('|', $wordKeywords) . ')\b/i';

        // 4. RECURSIVE CLEANUP
        $count = 0;
        do {
            $symbolCount = 0;
            $wordCount = 0;

            $sqlCleaned = str_ireplace($symbolKeywords, '', $sqlCleaned, $symbolCount);

            if ($wordPattern) {
                $sqlCleaned = preg_replace($wordPattern, '', $sqlCleaned, -1, $wordCount);
            }

            $count = $symbolCount + $wordCount;
            $total += $count;
        } while ($count > 0);

        // 5. RESTORE STRING LITERALS
        if (!empty($strings)) {
            foreach ($strings as $id => $originalString) {
                $sqlCleaned = str_replace($placeholderPrefix . $id . '__', $originalString, $sqlCleaned);
            }
        }

        return trim($sqlCleaned);
    }
}
