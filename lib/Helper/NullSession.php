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

class NullSession
{
    /**
     * Set UserId
     * @param $userId
     */
    public function setUser($userId): void
    {
        $_SESSION['userid'] = $userId;
    }

    /**
     * Updates the session ID with a new one
     */
    public function regenerateSessionId()
    {
    }

    /**
     * Set Expired
     * @param $isExpired
     */
    public function setIsExpired($isExpired)
    {
    }

    /**
     * Is the session expired?
     * There is no real session in this context (e.g. API), so report as expired
     * to keep session-dependent behaviour switched off.
     * @return bool
     */
    public function isExpired(): bool
    {
        return true;
    }

    /**
     * Store a variable in the session
     * @param string $key
     * @param mixed $secondKey
     * @param mixed|null $value
     * @return mixed
     */
    public static function set($key, $secondKey, $value = null): mixed
    {
        if (func_num_args() == 2) {
            return $secondKey;
        } else {
            return $value;
        }
    }

    /**
     * Get the Value from the position denoted by the 2 keys provided
     * @param string $key
     * @param string [Optional] $secondKey
     * @return bool
     */
    public static function get($key, $secondKey = null): bool
    {
        return false;
    }
}
