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
