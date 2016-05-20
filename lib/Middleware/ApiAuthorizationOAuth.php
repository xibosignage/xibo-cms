<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ApiAuthorizationOAuth.php)
 */


namespace Xibo\Middleware;


use Slim\Middleware;

class ApiAuthorizationOAuth extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // oAuth Resource
        $server = new \League\OAuth2\Server\AuthorizationServer;

        $server->setSessionStorage(new \Xibo\Storage\ApiSessionStorage($app->store));
        $server->setAccessTokenStorage(new \Xibo\Storage\ApiAccessTokenStorage($app->store));
        $server->setRefreshTokenStorage(new \Xibo\Storage\ApiRefreshTokenStorage($app->store));
        $server->setClientStorage(new \Xibo\Storage\ApiClientStorage($app->store));
        $server->setScopeStorage(new \Xibo\Storage\ApiScopeStorage($app->store));
        $server->setAuthCodeStorage(new \Xibo\Storage\ApiAuthCodeStorage($app->store));

        // Allow auth code grant
        $authCodeGrant = new \League\OAuth2\Server\Grant\AuthCodeGrant();
        $server->addGrantType($authCodeGrant);

        // Allow client credentials grant
        $clientCredentialsGrant = new \League\OAuth2\Server\Grant\ClientCredentialsGrant();
        $server->addGrantType($clientCredentialsGrant);

        // Add refresh tokens
        $refreshTokenGrant = new \League\OAuth2\Server\Grant\RefreshTokenGrant();
        $server->addGrantType($refreshTokenGrant);

        // Default scope
        $server->setDefaultScope('all');

        // DI in the server
        $app->server = $server;

        $this->next->call();
    }
}