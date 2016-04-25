<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiSessionStorage.php) is part of Xibo.
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
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\SessionInterface;

class ApiSessionStorage extends AbstractStorage implements SessionInterface
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
    public function getByAccessToken(AccessTokenEntity $accessToken)
    {
        $result = $this->getStore()->select('
            SELECT oauth_sessions.id, oauth_sessions.owner_type, oauth_sessions.owner_id, oauth_sessions.client_id, oauth_sessions.client_redirect_uri
              FROM oauth_sessions
                INNER JOIN oauth_access_tokens ON oauth_access_tokens.session_id =oauth_sessions.id
             WHERE oauth_access_tokens.access_token = :access_token
        ', [
            'access_token' => $accessToken->getId()
        ]);

        if (count($result) === 1) {
            $session = new SessionEntity($this->server);
            $session->setId($result[0]['id']);
            $session->setOwner($result[0]['owner_type'], $result[0]['owner_id']);

            return $session;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getByAuthCode(AuthCodeEntity $authCode)
    {
        $result = $this->getStore()->select('
            SELECT oauth_sessions.id, oauth_sessions.owner_type, oauth_sessions.owner_id, oauth_sessions.client_id, oauth_sessions.client_redirect_uri
              FROM oauth_sessions
                INNER JOIN oauth_auth_codes ON oauth_auth_codes.session_id = oauth_sessions.id
             WHERE oauth_auth_codes.auth_code = :auth_code
        ', [
            'auth_code' => $authCode->getId()
        ]);

        if (count($result) === 1) {
            $session = new SessionEntity($this->server);
            $session->setId($result[0]['id']);
            $session->setOwner($result[0]['owner_type'], $result[0]['owner_id']);

            return $session;
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(SessionEntity $session)
    {
        $result = $this->getStore()->select('
            SELECT oauth_scopes.*
              FROM oauth_sessions
                INNER JOIN oauth_session_scopes ON oauth_sessions.id = oauth_session_scopes.session_id
                INNER JOIN oauth_scopes ON oauth_scopes.id = oauth_session_scopes.scope
             WHERE oauth_sessions.id = :id
        ', [
            'id' => $session->getId()
        ]);

        $scopes = [];

        foreach ($result as $scope) {
            $scopes[] = (new ScopeEntity($this->server))->hydrate([
                'id'            =>  $scope['id'],
                'description'   =>  $scope['description'],
            ]);
        }

        return $scopes;
    }

    /**
     * {@inheritdoc}
     */
    public function create($ownerType, $ownerId, $clientId, $clientRedirectUri = null)
    {
        $id = $this->getStore()->insert('
            INSERT INTO oauth_sessions (owner_type, owner_id, client_id)
              VALUES (:owner_type, :owner_id, :client_id)
        ', [
            'owner_type'  =>    $ownerType,
            'owner_id'    =>    $ownerId,
            'client_id'   =>    $clientId,
        ]);

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function associateScope(SessionEntity $session, ScopeEntity $scope)
    {
        $this->getStore()->insert('
            INSERT INTO oauth_session_scopes (session_id, scope)
              VALUES (:session_id, :scope)
        ', [
            'session_id'    =>  $session->getId(),
            'scope'         =>  $scope->getId(),
        ]);
    }
}