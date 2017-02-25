<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Application.php)
 */


namespace Xibo\Entity;
use League\OAuth2\Server\Util\SecureKey;
use Xibo\Factory\ApplicationRedirectUriFactory;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Application
 * @package Xibo\Entity
 *
 * @SWG\Definition
 */
class Application implements \JsonSerializable
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
     * @var array[ApplicationRedirectUri]
     */
    public $redirectUris = [];

    /**
     * @var array[ApplicationScope]
     */
    public $scopes = [];

    /**
     * @var ApplicationRedirectUriFactory
     */
    private $applicationRedirectUriFactory;

    /** @var  ApplicationScopeFactory */
    private $applicationScopeFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param ApplicationScopeFactory $applicationScopeFactory
     */
    public function __construct($store, $log, $applicationRedirectUriFactory, $applicationScopeFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
    }

    /**
     * @param ApplicationRedirectUri $redirectUri
     */
    public function assignRedirectUri($redirectUri)
    {
        $this->load();

        // Assert client id
        $redirectUri->clientId = $this->key;

        if (!in_array($redirectUri, $this->redirectUris))
            $this->redirectUris[] = $redirectUri;
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
    public function assignScope($scope) {
        $this->load();

        if (!in_array($scope, $this->scopes))
            $this->scopes[] = $scope;
    }

    /**
     * @param ApplicationScope $scope
     */
    public function unassignScope($scope) {
        $this->load();

        $this->scopes = array_udiff($this->scopes, [$scope], function($a, $b) {
            /**
             * @var ApplicationScope $a
             * @var ApplicationScope $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded)
            return;

        $this->redirectUris = $this->applicationRedirectUriFactory->getByClientId($this->key);

        // Get scopes
        $this->scopes = $this->applicationScopeFactory->getByClientId($this->key);

        $this->loaded = true;
    }

    public function save()
    {
        if ($this->key == null || $this->key == '')
            $this->add();
        else
            $this->edit();

        $this->getLog()->debug('Saving redirect uris: %s', json_encode($this->redirectUris));

        foreach ($this->redirectUris as $redirectUri) {
            /* @var \Xibo\Entity\ApplicationRedirectUri $redirectUri */
            $redirectUri->save();
        }

        $this->manageScopeAssignments();
    }

    public function delete()
    {
        $this->load();

        foreach ($this->redirectUris as $redirectUri) {
            /* @var \Xibo\Entity\ApplicationRedirectUri $redirectUri */
            $redirectUri->delete();
        }

        // Clear out everything owned by this client
        $this->deleteTokens();
        $this->getStore()->update('DELETE FROM `oauth_session_scopes` WHERE id IN (SELECT session_id FROM `oauth_sessions` WHERE `client_id` = :id)', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_sessions` WHERE `client_id` = :id', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_client_scopes` WHERE `clientId` = :id', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_clients` WHERE `id` = :id', ['id' => $this->key]);
    }

    public function resetKeys()
    {
        $this->secret = SecureKey::generate(254);
        $this->deleteTokens();
    }

    private function deleteTokens()
    {
        $this->getStore()->update('DELETE FROM `oauth_access_token_scopes` WHERE access_token IN (SELECT access_token FROM `oauth_access_tokens` WHERE session_id IN (SELECT session_id FROM `oauth_sessions` WHERE `client_id` = :id))', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_refresh_tokens` WHERE access_token IN (SELECT access_token FROM `oauth_access_tokens` WHERE session_id IN (SELECT session_id FROM `oauth_sessions` WHERE `client_id` = :id))', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_access_tokens` WHERE session_id IN (SELECT session_id FROM `oauth_sessions` WHERE `client_id` = :id)', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_auth_code_scopes` WHERE auth_code IN (SELECT auth_code FROM `oauth_auth_codes` WHERE session_id IN (SELECT session_id FROM `oauth_sessions` WHERE `client_id` = :id))', ['id' => $this->key]);
        $this->getStore()->update('DELETE FROM `oauth_auth_codes` WHERE session_id IN (SELECT session_id FROM `oauth_sessions` WHERE `client_id` = :id)', ['id' => $this->key]);
    }

    private function add()
    {
        $this->key = SecureKey::generate();

        // Simple Insert for now
        $this->getStore()->insert('
            INSERT INTO `oauth_clients` (`id`, `secret`, `name`, `userId`, `authCode`, `clientCredentials`)
              VALUES (:id, :secret, :name, :userId, :authCode, :clientCredentials)
        ', [
            'id' => $this->key,
            'secret' => $this->secret,
            'name' => $this->name,
            'userId' => $this->userId,
            'authCode' => $this->authCode,
            'clientCredentials' => $this->clientCredentials
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
              `clientCredentials` = :clientCredentials
             WHERE `id` = :id
        ', [
            'id' => $this->key,
            'secret' => $this->secret,
            'name' => $this->name,
            'userId' => $this->userId,
            'authCode' => $this->authCode,
            'clientCredentials' => $this->clientCredentials
        ]);
    }

    /**
     * Compare the original assignments with the current assignments and delete any that are missing, add any new ones
     */
    private function manageScopeAssignments() {

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
}