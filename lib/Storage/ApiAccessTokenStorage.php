<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiAccessTokenStorage.php) is part of Xibo.
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


use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\AccessTokenInterface;

class ApiAccessTokenStorage extends AbstractStorage implements AccessTokenInterface
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
            SELECT *
              FROM oauth_access_tokens
             WHERE access_token = :access_token
        ', [
            'access_token' => $token
        ]);

        if (count($result) === 1) {
            $token = (new AccessTokenEntity($this->server))
                ->setId($result[0]['access_token'])
                ->setExpireTime($result[0]['expire_time']);

            return $token;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(AccessTokenEntity $token)
    {
        $result = $this->getStore()->select('
            SELECT oauth_scopes.id, oauth_scopes.description
              FROM oauth_access_token_scopes
                INNER JOIN oauth_scopes ON oauth_access_token_scopes.scope = oauth_scopes.id
             WHERE access_token = :access_token
        ', [
            'access_token' => $token
        ]);

        $response = [];

        if (count($result) > 0) {
            foreach ($result as $row) {
                $scope = (new ScopeEntity($this->server))->hydrate([
                    'id'            =>  $row['id'],
                    'description'   =>  $row['description'],
                ]);
                $response[] = $scope;
            }
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $sessionId)
    {
        $this->getStore()->insert('
            INSERT INTO oauth_access_tokens (access_token, session_id, expire_time)
              VALUES (:access_token, :session_id, :expire_time)
        ', [
            'access_token'     =>  $token,
            'session_id'    =>  $sessionId,
            'expire_time'   =>  $expireTime,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function associateScope(AccessTokenEntity $token, ScopeEntity $scope)
    {
        $this->getStore()->insert('
            INSERT INTO oauth_access_token_scopes (access_token, scope)
              VALUES (:access_token, :scope)
        ', [
            'access_token'  =>  $token->getId(),
            'scope' =>  $scope->getId(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(AccessTokenEntity $token)
    {
        $this->getStore()->update('DELETE FROM `oauth_access_token_scopes` WHERE access_token = :access_token', [ 'access_token' => $token->getId()]);
        $this->getStore()->update('DELETE FROM `oauth_access_tokens` WHERE access_token = :access_token', [ 'access_token' => $token->getId()]);
    }
}