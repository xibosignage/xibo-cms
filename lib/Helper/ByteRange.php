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
 * Parse an HTTP Range header for a single byte range of a file.
 */
class ByteRange
{
    /**
     * Anything other than one 'first-' or 'first-last' range, such as a suffix or several ranges, is ignored, which
     * RFC 9110 14.2 allows, and so is an invalid range whose last byte comes before its first (14.1.1).
     * @param string $rangeHeader the Range header, '' when the request has none
     * @param int $fileSize
     * @return array{0: int, 1: int}|false|null [start, end] inclusive, false for a 416, or null for the whole file
     */
    public static function parse(string $rangeHeader, int $fileSize): array|false|null
    {
        if ($rangeHeader === '' || !preg_match('/^bytes=(\d+)-(\d*)$/', trim($rangeHeader), $matches)) {
            return null;
        }

        $start = (int) $matches[1];
        if ($matches[2] !== '' && (int) $matches[2] < $start) {
            return null;
        }

        if ($start >= $fileSize) {
            return false;
        }

        // An end past the end of the file is clamped to it (RFC 9110 14.1.2).
        $end = $matches[2] !== '' ? min((int) $matches[2], $fileSize - 1) : $fileSize - 1;

        return [$start, $end];
    }
}
