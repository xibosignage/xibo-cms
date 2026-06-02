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

namespace Xibo\OAuth;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Stash\Interfaces\PoolInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    private PoolInterface $pool;

    public function __construct(PoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        // Stash truncates expiresAt by up to 15% to add variance, so pad the cache lifetime
        // to ensure the cache entry outlives the auth code's own validity window.
        $date = clone $authCodeEntity->getExpiryDateTime();
        $date = $date->add(new \DateInterval('P1D'));

        $cache = $this->pool->getItem('A_' . $authCodeEntity->getIdentifier());
        $cache->set(['used' => false]);
        $cache->expiresAt($date);
        $this->pool->saveDeferred($cache);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAuthCode($codeId)
    {
        // Marking as used rather than clearing the cache entry — we still want
        // isAuthCodeRevoked() to return true for the remainder of the original
        // validity window, not return "unknown" once the entry expires.
        $cache = $this->pool->getItem('A_' . $codeId);
        $cache->set(['used' => true]);
        $this->pool->saveDeferred($cache);
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthCodeRevoked($codeId)
    {
        $cache = $this->pool->getItem('A_' . $codeId);
        $data = $cache->get();

        // Cache miss means we have no record of this code being issued — treat as revoked
        // to prevent replay of codes we don't recognise (e.g. cache flush, codes from before
        // this repository was implemented).
        if ($cache->isMiss() || empty($data)) {
            return true;
        }

        return !empty($data['used']);
    }

    /**
     * {@inheritdoc}
     */
    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }
}
