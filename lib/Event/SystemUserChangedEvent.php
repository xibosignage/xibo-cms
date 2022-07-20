<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Event;

use Xibo\Entity\User;

class SystemUserChangedEvent extends Event
{
    public static $NAME = 'system.user.change.event';
    /**
     * @var User
     */
    private $oldSystemUser;
    /**
     * @var User
     */
    private $newSystemUser;

    public function __construct(User $oldSystemUser, User $newSystemUser)
    {
        $this->oldSystemUser = $oldSystemUser;
        $this->newSystemUser = $newSystemUser;
    }

    public function getOldSystemUser() : User
    {
        return $this->oldSystemUser;
    }

    public function getNewSystemUser() : User
    {
        return $this->newSystemUser;
    }
}
