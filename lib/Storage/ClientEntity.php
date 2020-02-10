<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

namespace Xibo\Storage;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use Xibo\Entity\EntityTrait;


class ClientEntity implements ClientEntityInterface
{
    use ClientTrait;
    use EntityTrait;

    /** @var string the secret hash */
    protected $secret;

    /** @var string the client identifier */
    protected $id;

    protected $userId;
    protected $name;

    /**
     * Get the hash for password verify
     * @return string
     */
    public function getHash()
    {
        return password_hash($this->secret, PASSWORD_DEFAULT);
    }

    /**
     * Get the client's identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->id;
    }

    public function getUserIdentifier()
    {
        return $this->userId;
    }
}
