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
 * The bandwidth a CDN reports for a file it served.
 */
class CdnBandwidth
{
    /**
     * @param string|null $header the X-CDN-BW header, null when the request has none
     * @param int $fallbackSize bytes to count when the CDN reports no usable amount
     * @return int bytes
     */
    public static function fromHeader(?string $header, int $fallbackSize): int
    {
        // Whole, non-negative numbers that fit an integer only.
        $bytes = $header === null
            ? false
            : filter_var(trim($header), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $bytes === false ? $fallbackSize : $bytes;
    }
}
