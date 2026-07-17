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

namespace Xibo\Helper;

/**
 * Sanitizes a candidate "return to this route" value (e.g. priorRoute query params,
 * SAML RelayState) before it's used as a redirect target, to prevent open redirects.
 */
class SafeRedirect
{
    /**
     * Sanitize a route value, stripping host/scheme and rejecting routes that
     * aren't a real destination to return to.
     * @param string|null $raw
     * @param string[] $rejectPrefixes Paths starting with any of these are treated as invalid.
     * @return string The sanitized path (+query/fragment), or '' if invalid.
     */
    public static function sanitizeRoute(?string $raw, array $rejectPrefixes = ['/login']): string
    {
        if (empty($raw)) {
            return '';
        }
        $parsed = parse_url($raw);
        if ($parsed === false || !empty($parsed['host'])) {
            return '';
        }
        $path = $parsed['path'] ?? '';
        if ($path === '' || $path === '/') {
            return '';
        }
        foreach ($rejectPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return '';
            }
        }
        $safe = $path;
        if (!empty($parsed['query'])) {
            $safe .= '?' . $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $safe .= '#' . $parsed['fragment'];
        }
        return $safe;
    }
}
