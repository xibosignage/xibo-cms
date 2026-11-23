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
 * Static class to reference statuses.
 */
class Status
{
    // Widget statuses.
    public static $STATUS_VALID = 1;
    public static $STATUS_PLAYER = 2;
    public static $STATUS_NOT_BUILT = 3;
    public static $STATUS_INVALID = 4;

    // Layout built without notifying displays (e.g. by preview or required files), Regular Maintenance notifies them
    public static $STATUS_PENDING_NOTIFY = 5;

    /**
     * Can a Layout with this status be played?
     * @param int $status
     * @return bool
     */
    public static function isPlayable(int $status): bool
    {
        return $status != self::$STATUS_INVALID;
    }
}
