<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiAuthCodeStorage.php) is part of Xibo.
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


use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\AuthCodeInterface;

class ApiAuthCodeStorage extends AbstractStorage implements AuthCodeInterface
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
    public function get($code)
    {
        $result = $this->getStore()->select('SELECT * FROM oauth_auth_codes WHERE auth_code = :auth_code AND expire_time >= :expire_time', array('auth_code' => $code, 'expire_time' => time()));

        if (count($result) === 1) {
            $token = new AuthCodeEntity($this->server);
            $token->setId($result[0]['auth_code']);
            $token->setRedirectUri($result[0]['client_redirect_uri']);
            $token->setExpireTime($result[0]['expire_time']);

            return $token;
        }

        return;
    }

    public function create($token, $expireTime, $sessionId, $redirectUri)
    {
        $this->getStore()->insert('
            INSERT INTO oauth_auth_codes (auth_code, client_redirect_uri, session_id, expire_time)
                VALUES (:auth_code, :client_redirect_uri, :session_id, :expire_time)
            ', [
            'auth_code'     =>  $token,
            'client_redirect_uri'  =>  $redirectUri,
            'session_id'    =>  $sessionId,
            'expire_time'   =>  $expireTime,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(AuthCodeEntity $token)
    {
        $result = $this->getStore()->select('
            SELECT oauth_scopes.id, oauth_scopes.description
              FROM oauth_auth_code_scopes
                INNER JOIN oauth_scopes
                ON oauth_auth_code_scopes.scope = oauth_scopes.id
             WHERE auth_code = :auth_code
        ', [
            'auth_code' => $token->getId()
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
    public function associateScope(AuthCodeEntity $token, ScopeEntity $scope)
    {
        $this->getStore()->insert('INSERT INTO oauth_auth_code_scopes (auth_code, scope) VALUES (:auth_code, :scope)', [
            'auth_code' =>  $token->getId(),
            'scope'     =>  $scope->getId(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(AuthCodeEntity $token)
    {
        $this->getStore()->update('DELETE FROM oauth_auth_codes WHERE auth_code = :auth_code', [
            'auth_code' =>  $token->getId()
        ]);
    }
}