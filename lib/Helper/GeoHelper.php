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
