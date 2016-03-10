<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiRefreshTokenStorage.php) is part of Xibo.
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


use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\RefreshTokenInterface;

class ApiRefreshTokenStorage extends AbstractStorage implements RefreshTokenInterface
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * ApiAccessTokenStorage constructor.
     * @param StorageServiceInterface $store
     */
    public function __construct($store)
    {
        if (!$store instanceof StorageServiceInterface)
            throw new \RuntimeException('Invalid $store');

        $this->store = $store;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * {@inheritdoc}
     */
    public function get($token)
    {
        $result = $this->getStore()->select('
            SELECT * FROM  oauth_refresh_tokens WHERE refresh_token = :refresh_token
        ', [
            'refresh_token' => $token
        ]);

        if (count($result) === 1) {
            $token = (new RefreshTokenEntity($this->server))
                ->setId($result[0]['refresh_token'])
                ->setExpireTime($result[0]['expire_time'])
                ->setAccessTokenId($result[0]['access_token']);

            return $token;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $accessToken)
    {
        $this->getStore()->insert('
            INSERT INTO oauth_refresh_tokens (refresh_token, access_token, expire_time)
              VALUES (:refresh_token, :access_token, :expire_time)
        ', [
            'refresh_token'     =>  $token,
            'access_token'    =>  $accessToken,
            'expire_time'   =>  $expireTime,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(RefreshTokenEntity $token)
    {
        $this->getStore()->update('DELETE FROM oauth_refresh_tokens WHERE refresh_token = :refresh_token', ['refresh_token' => $token->getId()]);
    }
}