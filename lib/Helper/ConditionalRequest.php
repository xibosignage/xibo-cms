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

namespace Xibo\Helper;

/**
 * Evaluate the conditional headers of a GET request (RFC 9110 13.1.2 and 13.1.3).
 */
class ConditionalRequest
{
    /**
     * Whether the client's copy is current, so a 304 can be sent instead of the file.
     * @param string|null $ifNoneMatch the If-None-Match header, null when the request has none
     * @param string|null $ifModifiedSince the If-Modified-Since header, null when the request has none
     * @param string $etag the file's ETag, quoted, or '' for none
     * @param int|null $lastModified the file's last modified time, as a Unix timestamp, or null for none
     * @return bool
     */
    public static function isNotModified(
        ?string $ifNoneMatch,
        ?string $ifModifiedSince,
        string $etag,
        ?int $lastModified
    ): bool {
        // If-None-Match takes precedence, and If-Modified-Since is ignored when it is present.
        if ($ifNoneMatch !== null && trim($ifNoneMatch) !== '') {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '*') {
                    return true;
                }
                if ($candidate !== '' && $etag !== ''
                    && self::withoutWeakPrefix($candidate) === self::withoutWeakPrefix($etag)
                ) {
                    return true;
                }
            }
            return false;
        }

        if ($ifModifiedSince !== null && $lastModified !== null) {
            $since = strtotime($ifModifiedSince);
            return $since !== false && $lastModified <= $since;
        }

        return false;
    }

    private static function withoutWeakPrefix(string $etag): string
    {
        return str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
    }
}
