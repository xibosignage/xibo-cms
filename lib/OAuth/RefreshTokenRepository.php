<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
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

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Stash\Interfaces\PoolInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @var \Xibo\Service\LogServiceInterface
     */
    private $logger;
    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * AccessTokenRepository constructor.
     * @param \Xibo\Service\LogServiceInterface $logger
     */
    public function __construct(\Xibo\Service\LogServiceInterface $logger, PoolInterface $pool)
    {
        $this->logger = $logger;
        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        // cache with refresh token identifier
        $cache = $this->pool->getItem('R_' . $refreshTokenEntity->getIdentifier());
        $cache->set(
            [
                'accessToken' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            ]
        );
        $cache->expiresAt($refreshTokenEntity->getExpiryDateTime());
        $this->pool->saveDeferred($cache);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeRefreshToken($tokenId)
    {
        $this->pool->getItem('R_' . $tokenId)->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function isRefreshTokenRevoked($tokenId)
    {
        // get cache by refresh token identifier
        $cache = $this->pool->getItem('R_' . $tokenId);
        $refreshTokenData = $cache->get();

        if ($cache->isMiss() || empty($refreshTokenData)) {
            return true;
        }

        // get access token cache by access token identifier
        $tokenCache = $this->pool->getItem('C_' . $refreshTokenData['accessToken']);
        $tokenCacheData = $tokenCache->get();

        // check access token cache by client and user identifiers
        // (see if application got changed secret/revoked access)
        $cache2 = $this->pool->getItem('C_' . $tokenCacheData['client'] . '/' . $tokenCacheData['userIdentifier']);
        $data2 = $cache2->get();

        if ($cache2->isMiss() || empty($data2)) {
            return true;
        }

        return false; // The refresh token has not been revoked
    }

    /**
     * {@inheritdoc}
     */
    public function getNewRefreshToken()
    {
        return new RefreshTokenEntity();
    }
}
