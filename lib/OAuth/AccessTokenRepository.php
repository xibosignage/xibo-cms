<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
        // authentication code, we have userIdentifier already
        $userIdentifier = $userIdentifier ?? $clientEntity->userId;

        $accessToken->setUserIdentifier($userIdentifier);

        // set user and log to audit
        $this->logger->setUserId($userIdentifier);
        $this->logger->audit(
            'Auth',
            0,
            'Access Token issued',
            [
                'Application identifier ends with' => substr($clientEntity->getIdentifier(), -8),
                'Application Name' => $clientEntity->getName()
            ]
        );

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
        // Note: we can only do this if one grant type is selected on the client.
        $client = $this->applicationFactory->getClientEntity($data['client']);
        if ($client->clientCredentials === 0
            && $client->authCode === 1
            && !$this->applicationFactory->checkAuthorised($data['client'], $data['userIdentifier'])
        ) {
            return true;
        }

        return false;
    }

    /** @inheritDoc */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $date = clone $accessTokenEntity->getExpiryDateTime();
        // since stash cache sets expiresAt at up to provided date
        // with up to 15% less than the provided date
        // add more time to normal token expire, to ensure cache does not expire before the token.
        $date = $date->add(new \DateInterval('PT30M'));

        // cache with token identifier
        $cache = $this->pool->getItem('C_' . $accessTokenEntity->getIdentifier());

        $cache->set(
            [
                'userIdentifier' => $accessTokenEntity->getUserIdentifier(),
                'client' => $accessTokenEntity->getClient()->getIdentifier()
            ]
        );
        $cache->expiresAt($date);
        $this->pool->saveDeferred($cache);

        // double cache with client identifier and user identifier
        // this will allow us to revoke access to client or for specific client/user combination in the backend
        $cache2 = $this->pool->getItem(
            'C_' . $accessTokenEntity->getClient()->getIdentifier() . '/' . $accessTokenEntity->getUserIdentifier()
        );

        $cache2->set(
            [
                'userIdentifier' => $accessTokenEntity->getUserIdentifier(),
                'client' => $accessTokenEntity->getClient()->getIdentifier()
            ]
        );

        $cache2->expiresAt($date);
        $this->pool->saveDeferred($cache2);
    }

    /** @inheritDoc */
    public function revokeAccessToken($tokenId)
    {
        $this->pool->getItem('C_' . $tokenId)->clear();
    }
}
