<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiScopeStorage.php) is part of Xibo.
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


use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\ScopeInterface;

class ApiScopeStorage extends AbstractStorage implements ScopeInterface
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
    public function get($scope, $grantType = null, $clientId = null)
    {
        $result = $this->getStore()->select('SELECT * FROM oauth_scopes WHERE id = :id ', array('id' => $scope));

        if (count($result) === 0) {
            return;
        }

        if ($clientId !== null) {
            // Check to see if this scope exists for this client
            $clientScope = $this->getStore()->select('SELECT * FROM `oauth_client_scopes` WHERE clientId = :clientId AND scopeId = :id', [
                'clientId' => $clientId,
                'id' => $scope
            ]);

            if (count($clientScope) == 0)
                return;
        }

        return (new ScopeEntity($this->server))->hydrate([
            'id'            =>  $result[0]['id'],
            'description'   =>  $result[0]['description'],
        ]);
    }
}