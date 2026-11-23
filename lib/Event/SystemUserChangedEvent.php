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
