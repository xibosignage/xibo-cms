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


namespace Xibo\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Xibo\Factory\ApplicationRedirectUriFactory;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Helper\Random;
use Xibo\OAuth\ScopeEntity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Application
 * @package Xibo\Entity
 *
 * @SWG\Definition
 */
class Application implements \JsonSerializable, ClientEntityInterface
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="Application Key"
     * )
     * @var string
     */
    public $key;

    /**
     * @SWG\Property(
     *  description="Private Secret Key"
     * )
     * @var string
     */
    public $secret;

    /**
     * @SWG\Property(
     *  description="Application Name"
     * )
     * @var string
     */
    public $name;
    
    /**
     * @SWG\Property(
     *  description="Application Owner"
     * )
     * @var string
     */
    public $owner;

    /**
     * @SWG\Property(
     *  description="Application Session Expiry"
     * )
     * @var int
     */
    public $expires;

    /**
     * @SWG\Property(
     *  description="The Owner of this Application"
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="Flag indicating whether to allow the authorizationCode Grant Type")
     * @var int
     */
    public $authCode = 0;

    /**
     * @SWG\Property(description="Flag indicating whether to allow the clientCredentials Grant Type")
     * @var int
     */
    public $clientCredentials = 0;

    /**
     * @SWG\Property(description="Flag indicating whether this Application will be confidential or not (can it keep a secret?)")
     * @var int
     */
    public $isConfidential = 1;

    /** * @var ApplicationRedirectUri[] */
    public $redirectUris = [];

    /** * @var ApplicationScope[] */
    public $scopes = [];

    /**
     * @SWG\Property(description="Application description")
     * @var string
     */
    public $description;
    /**
     * @SWG\Property(description="Path to Application logo")
     * @var string
     */
    public $logo;
    /**
     * @SWG\Property(description="Path to Application Cover Image")
     * @var string
     */
    public $coverImage;
    /**
     * @SWG\Property(description="Company name associated with this Application")
     * @var string
     */
    public $companyName;
    /**
     * @SWG\Property(description="URL to Application terms")
     * @var string
     */
    public $termsUrl;
    /**
     * @SWG\Property(description="URL to Application privacy policy")
     * @var string
     */
    public $privacyUrl;

    /** @var ApplicationRedirectUriFactory */
    private $applicationRedirectUriFactory;

    /** @var  ApplicationScopeFactory */
    private $applicationScopeFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param ApplicationScopeFactory $applicationScopeFactory
     */
    public function __construct($store, $log, $dispatcher, $applicationRedirectUriFactory, $applicationScopeFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
    }

    public function __serialize(): array
    {
        return $this->jsonSerialize();
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @param ApplicationRedirectUri $redirectUri
     */
    public function assignRedirectUri($redirectUri)
    {
        $this->load();

        // Assert client id
        $redirectUri->clientId = $this->key;

        if (!in_array($redirectUri, $this->redirectUris)) {
            $this->redirectUris[] = $redirectUri;
        }
    }

    /**
     * Unassign RedirectUri
     * @param ApplicationRedirectUri $redirectUri
     */
    public function unassignRedirectUri($redirectUri)
    {
        $this->load();

        $this->redirectUris = array_udiff($this->redirectUris, [$redirectUri], function($a, $b) {
            /**
             * @var ApplicationRedirectUri $a
             * @var ApplicationRedirectUri $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * @param ApplicationScope $scope
     */
    public function assignScope($scope)
    {
        if (!in_array($scope, $this->scopes)) {
            $this->scopes[] = $scope;
        }

        return $this;
    }

    /**
     * @param ApplicationScope $scope
     */
    public function unassignScope($scope)
    {
        $this->scopes = array_udiff($this->scopes, [$scope], function ($a, $b) {
            /**
             * @var ApplicationScope $a
             * @var ApplicationScope $b
             */
            return $a->getId() !== $b->getId();
        });
    }

    /**
     * Get the hash for password verify
     * @return string
     */
    public function getHash()
    {
        return password_hash($this->secret, PASSWORD_DEFAULT);
    }

    /**
     * Load
     * @return $this
     */
    public function load()
    {
        if ($this->loaded || empty($this->key)) {
            return $this;
        }

        // Redirects
        $this->redirectUris = $this->applicationRedirectUriFactory->getByClientId($this->key);

        // Get scopes
        $this->scopes = $this->applicationScopeFactory->getByClientId($this->key);

        $this->loaded = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function save()
    {
        if ($this->key == null || $this->key == '') {
            // Make a new secret.
            $this->resetSecret();

            // Add
            $this->add();
        } else {
            // Edit
            $this->edit();
        }

        $this->getLog()->debug('Saving redirect uris: ' . json_encode($this->redirectUris));

        foreach ($this->redirectUris as $redirectUri) {
            $redirectUri->save();
        }

        $this->manageScopeAssignments();

        return $this;
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->load();

        foreach ($this->redirectUris as $redirectUri) {
            $redirectUri->delete();
        }

        // Clear link table for this Application
        $this->getStore()->update('DELETE FROM `oauth_lkclientuser` WHERE clientId = :id', ['id' => $this->key]);

        // Clear out everything owned by this client
        $this->getStore()->update('DELETE FROM `oauth_client_scopes` WHERE `clientId` = :id', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_clients` WHERE `id` = :id', ['id' => $this->key]);
    }

    /**
     * Reset Secret
     */
    public function resetSecret()
    {
        $this->secret = Random::generateString(254);
    }

    private function add()
    {
        // Make an ID
        $this->key = Random::generateString(40);

        // Simple Insert for now
        $this->getStore()->insert('
            INSERT INTO `oauth_clients` (`id`, `secret`, `name`, `userId`, `authCode`, `clientCredentials`, `isConfidential`, `description`, `logo`, `coverImage`, `companyName`, `termsUrl`, `privacyUrl`)
              VALUES (:id, :secret, :name, :userId, :authCode, :clientCredentials, :isConfidential, :description, :logo, :coverImage, :companyName, :termsUrl, :privacyUrl)
        ', [
            'id' => $this->key,
            'secret' => $this->secret,
            'name' => $this->name,
            'userId' => $this->userId,
            'authCode' => $this->authCode,
            'clientCredentials' => $this->clientCredentials,
            'isConfidential' => $this->isConfidential,
            'description' => $this->description,
            'logo' => $this->logo,
            'coverImage' => $this->coverImage,
            'companyName' => $this->companyName,
            'termsUrl' => $this->termsUrl,
            'privacyUrl' => $this->privacyUrl
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
            UPDATE `oauth_clients` SET
              `id` = :id,
              `secret` = :secret,
              `name` = :name,
              `userId` = :userId,
              `authCode` = :authCode,
              `clientCredentials` = :clientCredentials,
              `isConfidential` = :isConfidential,
              `description` = :description,
              `logo` = :logo,
              `coverImage` = :coverImage,
              `companyName` = :companyName,
              `termsUrl` = :termsUrl,
              `privacyUrl` = :privacyUrl
             WHERE `id` = :id
        ', [
            'id' => $this->key,
            'secret' => $this->secret,
            'name' => $this->name,
            'userId' => $this->userId,
            'authCode' => $this->authCode,
            'clientCredentials' => $this->clientCredentials,
            'isConfidential' => $this->isConfidential,
            'description' => $this->description,
            'logo' => $this->logo,
            'coverImage' => $this->coverImage,
            'companyName' => $this->companyName,
            'termsUrl' => $this->termsUrl,
            'privacyUrl' => $this->privacyUrl
        ]);
    }

    /**
     * Compare the original assignments with the current assignments and delete any that are missing, add any new ones
     */
    private function manageScopeAssignments()
    {
        $i = 0;
        $params = ['clientId' => $this->key];
        $unassignIn = '';

        foreach ($this->scopes as $link) {
            $this->getStore()->update('
              INSERT INTO `oauth_client_scopes` (clientId, scopeId) VALUES (:clientId, :scopeId)
              ON DUPLICATE KEY UPDATE scopeId = scopeId', [
                'clientId' => $this->key,
                'scopeId' => $link->id
            ]);

            $i++;
            $unassignIn .= ',:scopeId' . $i;
            $params['scopeId' . $i] = $link->id;
        }

        // Unlink any NOT in the collection
        $sql = 'DELETE FROM `oauth_client_scopes` WHERE clientId = :clientId AND scopeId NOT IN (\'0\'' . $unassignIn . ')';

        $this->getStore()->update($sql, $params);
    }

    /** @inheritDoc */
    public function getIdentifier()
    {
        return $this->key;
    }

    /** @inheritDoc */
    public function getName()
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function getRedirectUri()
    {
        $count = count($this->redirectUris);

        if ($count <= 0) {
            return null;
        } else if (count($this->redirectUris) == 1) {
            return $this->redirectUris[0]->redirectUri;
        } else {
            return array_map(function($el) {
                return $el->redirectUri;
            }, $this->redirectUris);
        }
    }

    /**
     * @return \League\OAuth2\Server\Entities\ScopeEntityInterface[]
     */
    public function getScopes()
    {
        $scopes = [];
        foreach ($this->scopes as $applicationScope) {
            $scope = new ScopeEntity();
            $scope->setIdentifier($applicationScope->getId());
            $scopes[] = $scope;
        }
        return $scopes;
    }

    /** @inheritDoc */
    public function isConfidential()
    {
        return $this->isConfidential === 1;
    }
}
