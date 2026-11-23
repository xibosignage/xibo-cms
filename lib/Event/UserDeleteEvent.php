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

class UserDeleteEvent extends Event
{
    public static $NAME = 'user.delete.event';

    /** @var User */
    private $user;

    /** @var User */
    private $newUser;

    /** @var string */
    private $function;

    /** @var User */
    private $systemUser;

    public $returnValue;

    /**
     * UserDeleteEvent constructor.
     * @param $user
     * @param $function
     */
    public function __construct($user, $function, $systemUser = null, $newUser = null)
    {
        $this->user = $user;
        $this->newUser = $newUser;
        $this->systemUser = $systemUser;
        $this->function = $function;
    }

    /**
     * @return User
     */
    public function getUser() : User
    {
        return $this->user;
    }

    public function getNewUser()
    {
        return $this->newUser;
    }

    public function getSystemUser() : User
    {
        return $this->systemUser;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    public function setReturnValue($returnValue)
    {
        $this->returnValue = $returnValue;
    }

    public function getReturnValue()
    {
        return $this->returnValue;
    }
}
