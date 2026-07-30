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

class LibraryDescription
{
    /**
     * Get the media released description
     * @param $released
     * @return string
     */
    public static function getMediaReleasedDescription($released): string
    {
        return match ($released) {
            1 => '',
            2 => __('The uploaded image is too large and cannot be processed, please use another image.'),
            default => __('This image will be resized according to set thresholds and limits.'),
        };
    }

    /**
     * Get the media enable stat description
     * @param $enableStat
     * @return string
     */
    public static function getMediaEnableStatDescription($enableStat): string
    {
        return match ($enableStat) {
            'On' => __('This Media has enable stat collection set to ON'),
            'Off' => __('This Media has enable stat collection set to OFF'),
            default => __('This Media has enable stat collection set to INHERIT'),
        };
    }
}
