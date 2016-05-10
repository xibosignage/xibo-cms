<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiClientStorage.php) is part of Xibo.
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

use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\ClientInterface;

class ApiClientStorage extends AbstractStorage implements ClientInterface
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
    public function get($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
        $sql = '
            SELECT oauth_clients.*
              FROM oauth_clients
             WHERE oauth_clients.id = :clientId
        ';
        $params = [
            'clientId' => $clientId
        ];

        if ($redirectUri) {
            $sql = '
                SELECT oauth_clients.*
                  FROM oauth_clients
                    INNER JOIN oauth_client_redirect_uris ON oauth_clients.id = oauth_client_redirect_uris.client_id
                 WHERE oauth_clients.id = :clientId
                  AND oauth_client_redirect_uris.redirect_uri = :redirect_uri
            ';

            $params['redirect_uri'] = $redirectUri;
        }

        if ($clientSecret !== null) {
            $sql .= ' AND oauth_clients.secret = :clientSecret ';
            $params['clientSecret'] = $clientSecret;
        }

        $result = $this->getStore()->select($sql, $params);

        if (count($result) === 1) {
            $client = new ClientEntity($this->server);
            $client->hydrate([
                'id'    =>  $result[0]['id'],
                'name'  =>  $result[0]['name'],
            ]);

            // Check to see if this grant_type is allowed for this client
            switch ($grantType) {

                case 'authorization_code':
                    if ($result[0]['authCode'] != 1)
                        return false;

                    break;

                case 'client_credentials':
                case 'mcaas':
                    if ($result[0]['clientCredentials'] != 1)
                        return false;

                    break;

                default:
                    return false;
            }

            return $client;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getBySession(SessionEntity $session)
    {
        $result = $this->getStore()->select('
            SELECT oauth_clients.id, oauth_clients.name
              FROM oauth_clients
                INNER JOIN oauth_sessions ON oauth_clients.id = oauth_sessions.client_id
             WHERE oauth_sessions.id = :id
        ', [
            'id' => $session->getId()
        ]);

        if (count($result) === 1) {
            $client = new ClientEntity($this->server);
            $client->hydrate([
                'id'    =>  $result[0]['id'],
                'name'  =>  $result[0]['name'],
            ]);

            return $client;
        }

        return false;
    }
}