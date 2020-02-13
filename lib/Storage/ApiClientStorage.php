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

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * Class ApiClientStorage
 * @package Xibo\Storage
 */
class ApiClientStorage implements ClientRepositoryInterface
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var \Xibo\Service\LogServiceInterface
     */
    private $logger;

    /**
     * ApiAccessTokenStorage constructor.
     * @param StorageServiceInterface $store
     * @param \Xibo\Service\LogServiceInterface $logger
     */
    public function __construct($store, $logger)
    {
        if (!$store instanceof StorageServiceInterface) {
            throw new \RuntimeException('Invalid $store');
        }

        $this->store = $store;
        $this->logger = $logger;
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
     * Get Store
     * @return \Xibo\Service\LogServiceInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier)
    {
        $sql = '
            SELECT oauth_clients.*
              FROM oauth_clients
             WHERE oauth_clients.id = :clientId
        ';

        $params = [
            'clientId' => $clientIdentifier
        ];

        $result = $this->getStore()->select($sql, $params);

        if ($result[0] === null) {
            $this->getLogger()->debug('Unable to find ' . $clientIdentifier);
            return null;
        }

        $this->getLogger()->debug('getClientEntity for ' . $clientIdentifier . ', found ' . var_export($result[0], true));
        if ($result[0]['authCode'] == 1) {
            $result[0]['isConfidential'] = true;
        }
        return (new ClientEntity())->hydrate($result[0], ['stringProperties' => ['id']]);
    }

    /**
     * {@inheritdoc}
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {

        $client = $this->getClientEntity($clientIdentifier);
        $this->getLogger()->debug('validateClient for ' . $clientIdentifier . ' secret ' . $clientSecret . ' and hash ' . $client->getHash());

        if ($client === null) {
            return false;
        }

        if (
            $client->isConfidential() === true
            && password_verify($clientSecret, $client->getHash()) === false
        ) {
            return false;
        }

        return true;
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
            $client = new ClientEntity();
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
}