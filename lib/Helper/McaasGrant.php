<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (McaasGrant.php)
 */


namespace Xibo\Helper;


use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Exception\InvalidClientException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Util\SecureKey;

/**
 * Class McaasGrant
 * @package Xibo\Helper
 */
class McaasGrant extends AbstractGrant
{
    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'mcaas';

    /**
     * Response type
     *
     * @var string
     */
    protected $responseType = null;

    /**
     * AuthServer instance
     *
     * @var \League\OAuth2\Server\AuthorizationServer
     */
    protected $server = null;

    /**
     * Access token expires in override
     *
     * @var int
     */
    protected $accessTokenTTL = null;

    /**
     * @var ClientEntity
     */
    protected $client;

    /**
     * Set Client
     * @param string $clientId
     * @param string $clientSecret
     * @return self
     * @throws InvalidClientException
     */
    public function setClient($clientId, $clientSecret)
    {
        $this->client = $this->server->getClientStorage()->get(
            $clientId,
            $clientSecret,
            null,
            $this->getIdentifier()
        );

        if (($this->client instanceof ClientEntity) === false) {
            throw new InvalidClientException();
        }

        return $this;
    }

    /**
     * Complete the client credentials grant
     *
     * @return string
     *
     * @throws
     */
    public function completeFlow()
    {
        // Validate any scopes that are in the request (should always return default)
        $scopes = $this->validateScopes('', $this->client);

        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner('client', $this->client->getId());
        $session->associateClient($this->client);

        // Generate an access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());

        // Associate scopes with the session and access token
        foreach ($scopes as $scope) {
            $session->associateScope($scope);
        }

        foreach ($session->getScopes() as $scope) {
            $accessToken->associateScope($scope);
        }

        // Save everything
        $session->save();
        $accessToken->setSession($session);
        $accessToken->save();

        return $accessToken->getId();
    }
}