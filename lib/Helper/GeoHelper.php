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
 * Class GeoHelper
 * @package Xibo\Helper
 */
class GeoHelper
{
    /**
     * Default precision (decimal places) coordinates are rounded to.
     * 2 decimal places is approximately 1.1km, which is small enough that weather data
     * won't meaningfully differ, while allowing nearby displays to share cache entries
     * and outbound API calls.
     */
    public const int COORDINATE_PRECISION = 2;

    /**
     * Round a coordinate (latitude or longitude) to a fixed precision so that displays
     * which are close together resolve to the same value, and therefore share cache
     * entries and outbound API calls.
     * @param mixed $value
     * @param int $precision
     * @return float|null
     */
    public static function roundCoordinate($value, int $precision = self::COORDINATE_PRECISION): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $precision);
    }
}
