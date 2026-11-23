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
