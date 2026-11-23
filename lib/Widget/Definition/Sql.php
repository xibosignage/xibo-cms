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

namespace Xibo\Widget\Definition;

use Xibo\Support\Exception\InvalidArgumentException;

/**
 * SQL definitions and boundary-sanitizer for DataSet filter/formula fragments.
 *
 * The Xibo DataSet pipeline assembles WHERE-clause fragments from multiple
 * sources (clause-builder in DataSetDataProviderListener, raw filter on
 * DataSet entity, RSS feed clause-builder in DataSetRss). Every assembly
 * path converges at DataSet::getData(), which calls Sql::sanitizeFragment()
 * — the SINGLE point of sanitization. The clause builders escape their values
 * with escapeLiteral() so they produce valid SQL, not as a security defence.
 *
 * cleanup() is the low-level strip-only primitive (recursive keyword
 * removal, string-literal preservation, comment / hex / binary stripping).
 * sanitizeFragment() is the throw-on-detection wrapper that production
 * callers should use.
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
        'CONCAT_WS', 'CHAR', 'UNHEX', 'HEX', 'ASCII', 'BIN', 'ORD', 'BASE64',
        // Resource-exhaustion functions
        'REPEAT', 'SPACE', 'LPAD', 'RPAD', 'RANDOM_BYTES',
        'SHA', 'SHA1', 'SHA2', 'MD5', 'AES_ENCRYPT', 'AES_DECRYPT',
        'COMPRESS', 'UNCOMPRESS',
        // Regex functions (ReDoS risk - unbounded backtracking complexity)
        'REGEXP_REPLACE', 'REGEXP_SUBSTR', 'REGEXP_LIKE', 'REGEXP_INSTR', 'RLIKE',
    ];

    /**
     * Validate that a string is safe to use as an unquoted SQL identifier
     * (table name, column name, etc.).
     *
     * Identifiers cannot be bound as PDO parameters, so they have to be
     * concatenated into SQL strings. The traits in lib/Entity/, lib/Report/, and
     * lib/Factory/ that accept table/column-name parameters (TagLinkTrait,
     * EntityTrait, ReportDefaultTrait, TagTrait) route those parameters through
     * this helper so a future caller that ever passes user-influenced input
     * fails loudly at the trait boundary rather than producing SQL-identifier
     * injection at the sink.
     *
     * The pattern `^[A-Za-z_][A-Za-z0-9_]*$` matches the standard SQL
     * identifier syntax (alphanumeric + underscore, starting with a letter or
     * underscore). It rejects backticks, quotes, whitespace, dots, hyphens,
     * and every other character that could escape an identifier context.
     *
     * @param string $id The identifier to validate.
     * @param string $context Field name for the error message (e.g. 'table',
     *                        'column').
     * @return string The identifier (unchanged) when valid.
     * @throws InvalidArgumentException If the identifier fails the regex.
     */
    public static function validateIdentifier(string $id, string $context): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $id)) {
            throw new InvalidArgumentException(
                sprintf(__('Invalid SQL identifier for %s: %s'), $context, $id),
                $context
            );
        }
        return $id;
    }

    /**
     * Escape a value for use inside a single-quoted SQL string literal (O'Brien becomes O''Brien).
     */
    public static function escapeLiteral(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\'\''], $value);
    }

    /**
     * Throw-on-detection wrapper around cleanup(). Production callers SHOULD use
     * this rather than cleanup() directly — silently dropping disallowed keywords
     * masks the attack signal and produces broken SQL fragments downstream.
     *
     * @param string $sql The SQL fragment to sanitize.
     * @param string $context Field name (e.g. 'filter', 'keyword') used in the
     *                        thrown exception so the caller can identify the
     *                        offending parameter.
     * @return string The sanitized fragment (unchanged if no disallowed keywords
     *                were present).
     * @throws InvalidArgumentException If the fragment contained an unclosed quote,
     *         any DISALLOWED_KEYWORDS, hex/binary literals, stripped comments, or a
     *         quoted name used as a function call.
     */
    public static function sanitizeFragment(string $sql, string $context): string
    {
        $count = 0;
        $strayQuotes = 0;
        $cleaned = self::cleanup($sql, $count, $strayQuotes);
        if ($strayQuotes > 0) {
            throw new InvalidArgumentException(
                sprintf(__('%s has an unclosed quote'), ucfirst($context)),
                $context
            );
        }
        if ($count > 0) {
            throw new InvalidArgumentException(
                sprintf(__('%s contains disallowed keywords'), ucfirst($context)),
                $context
            );
        }
        return $cleaned;
    }

    /**
     * Cleanup SQL (Maximum Paranoia for Legacy Code)
     *
     * Low-level strip-only primitive. Prefer sanitizeFragment() — its throw-on-
     * detection semantics surface attack attempts instead of silently rewriting
     * to a broken fragment. cleanup() remains public because the DataSet formula
     * path (DataSet.php:560) intentionally tolerates disallowed keywords by
     * silently skipping the affected column rather than failing the whole query.
     *
     * @param string $sql the SQL to clean
     * @param int $total the total number of replacements
     * @param int $strayQuotes how many of those were quotes or backslashes outside a literal
     * @return string
     */
    public static function cleanup(string $sql, int &$total = 0, int &$strayQuotes = 0): string
    {
        // 1. EXTRACT AND PROTECT STRING LITERALS (Preserve user data)
        $strings = [];
        // Braces keep keyword word-boundaries intact; the nonce stops input forging a placeholder
        $nonce = bin2hex(random_bytes(8));
        // Same literal boundaries as MySQL's lexer, including backtick identifiers
        $stringPattern = '/(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|`(?:``|[^`])*`)/s';

        $sqlCleaned = preg_replace_callback($stringPattern, function ($matches) use (&$strings, $nonce) {
            $id = count($strings);
            $strings[] = $matches[0];
            return '{' . $nonce . '_' . $id . '}';
        }, $sql);

        // A quote, backslash or backtick left outside a literal means our boundaries may differ from MySQL's
        $sqlCleaned = preg_replace('/[\'"\\\\`]/', '', $sqlCleaned, -1, $strayCount);
        $total += $strayCount;
        $strayQuotes += $strayCount;

        // 2. STRIP COMMENTS & ENCODINGS (Before keyword checks)
        $preCleanupPatterns = [
            '/(?:\/\*.*?\*\/|--(?:[\x00-\x20].*?)?(?:\n|$)|#[^\n]*?(?:\n|$))/s', // Standard & Executable Comments
            '/\/\*|\*\//',                                            // Unpaired comment markers
            '/\b0x[0-9a-fA-F]+\b/',                                   // Hex literals (e.g., 0x7e)
            '/\bb\'[01]+\'/i'                                         // Binary literals
        ];
        $sqlCleaned = preg_replace($preCleanupPatterns, '', $sqlCleaned, -1, $preCleanupCount);
        $total += $preCleanupCount;

        // A protected literal followed by "(" is a call (`SLEEP`(1)), which would hide the name from the checks below
        $sqlCleaned = preg_replace('/\{' . $nonce . '_\d+\}(?=\s*\()/', '', $sqlCleaned, -1, $callCount);
        $total += $callCount;

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
        // Single pass, so placeholder text inside a restored literal is never expanded
        if (!empty($strings)) {
            $sqlCleaned = preg_replace_callback(
                '/\{' . $nonce . '_(\d+)\}/',
                fn ($matches) => $strings[(int)$matches[1]],
                $sqlCleaned
            );
        }

        return trim($sqlCleaned);
    }
}
