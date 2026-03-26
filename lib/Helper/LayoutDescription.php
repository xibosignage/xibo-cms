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

class LayoutDescription
{
    /**
     * Get the layout status description
     * @param $status
     * @return string
     */
    public static function getLayoutStatusDescription($status): string
    {
        return match ($status) {
            Status::$STATUS_VALID => __('This Layout is ready to play'),
            Status::$STATUS_PLAYER => __('There are items on this Layout that can only be assessed by the Display'),
            Status::$STATUS_NOT_BUILT => __('This Layout has not been built yet'),
            default => __('This Layout is invalid and should not be scheduled'),
        };
    }

    /**
     * Get the layout enable stat description
     * @param $enableStat
     * @return string
     */
    public static function getLayoutEnableStatDescription($enableStat): string
    {
        return match ($enableStat) {
            1 => __('This Layout has enable stat collection set to ON'),
            default => __('This Layout has enable stat collection set to OFF'),
        };
    }
}
