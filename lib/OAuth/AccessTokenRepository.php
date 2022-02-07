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

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\ApplicationFactory;

/**
 * Class AccessTokenRepository
 * @package Xibo\Storage
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /** @var \Xibo\Service\LogServiceInterface*/
    private $logger;
    /**
     * @var ApplicationFactory
     */
    private $applicationFactory;
    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * AccessTokenRepository constructor.
     * @param \Xibo\Service\LogServiceInterface $logger
     */
    public function __construct($logger, PoolInterface $pool, ApplicationFactory $applicationFactory)
    {
        $this->logger = $logger;
        $this->pool = $pool;
        $this->applicationFactory = $applicationFactory;
    }

    /** @inheritDoc */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $this->logger->debug('Getting new Access Token');

        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        // client credentials, we take user from the client entity
        if ($userIdentifier === null) {
            $accessToken->setUserIdentifier($clientEntity->userId);
        } else {
            // authentication code, we should have a userIdentifier here
            $accessToken->setUserIdentifier($userIdentifier);
        }
        return $accessToken;
    }

    /** @inheritDoc */
    public function isAccessTokenRevoked($tokenId)
    {
        $cache = $this->pool->getItem('C_' . $tokenId);
        $data = $cache->get();

        // if cache is expired
        if ($cache->isMiss() || empty($data)) {
            return true;
        }

        $cache2 = $this->pool->getItem('C_' . $data['client'] . '/' . $data['userIdentifier']);
        $data2 = $cache2->get();

        // cache manually removed (revoke access, changed secret)
        if ($cache2->isMiss() || empty($data2)) {
            return true;
        }

        if ($data['client'] !== $data2['client'] || $data['userIdentifier'] !== $data2['userIdentifier']) {
            return true;
        }

        // if it is correctly cached, double check that it is still authorized at the request time
        // edge case being new access code requested with not yet expired code,
        // otherwise one of the previous conditions will be met.
        $client = $this->applicationFactory->getClientEntity($data['client']);
        if ($client->authCode === 1 && !$this->applicationFactory->checkAuthorised($data['client'], $data['userIdentifier'])) {
            return true;
        }

        return false;
    }

    /** @inheritDoc */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        // cache with token identifier
        $cache = $this->pool->getItem('C_' . $accessTokenEntity->getIdentifier());
        $cache->set(
            [
                'userIdentifier' => $accessTokenEntity->getUserIdentifier(),
                'client' => $accessTokenEntity->getClient()->getIdentifier()
            ]
        );
        $cache->expiresAt($accessTokenEntity->getExpiryDateTime());
        $this->pool->saveDeferred($cache);

        // double cache with client identifier and user identifier
        // this will allow us to revoke access to client or for specific client/user combination in the backend
        $cache2 = $this->pool->getItem('C_' . $accessTokenEntity->getClient()->getIdentifier() . '/' . $accessTokenEntity->getUserIdentifier());
        $cache2->set(
            [
                'userIdentifier' => $accessTokenEntity->getUserIdentifier(),
                'client' => $accessTokenEntity->getClient()->getIdentifier()
            ]
        );
        $cache2->expiresAt($accessTokenEntity->getExpiryDateTime());
        $this->pool->saveDeferred($cache2);
    }

    /** @inheritDoc */
    public function revokeAccessToken($tokenId)
    {
        $this->pool->getItem('C_' . $tokenId)->clear();
    }
}
