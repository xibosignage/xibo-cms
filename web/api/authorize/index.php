<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (api.php) is part of Xibo.
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

use Xibo\Helper\Config;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../../..'));

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

Config::Load(PROJECT_ROOT . '/web/settings.php');

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'AUTH',
    'handlers' => array(
        new \Xibo\Helper\DatabaseLogHandler()
    ),
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

$app = new \Slim\Slim(array(
    'mode' => Config::GetSetting('SERVER_MODE'),
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('auth');

$app->add(new \Xibo\Middleware\Storage());
$app->add(new \Xibo\Middleware\State());
$app->view(new \Xibo\Middleware\ApiView());

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $controller = new \Xibo\Controller\Error();
    $controller->handler($e);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $controller = new \Xibo\Controller\Error();
    $controller->notFound();
});

// oAuth Resource
$server = new \League\OAuth2\Server\AuthorizationServer;

$server->setSessionStorage(new \Xibo\Storage\ApiSessionStorage());
$server->setAccessTokenStorage(new \Xibo\Storage\ApiAccessTokenStorage());
$server->setRefreshTokenStorage(new \Xibo\Storage\ApiRefreshTokenStorage());
$server->setClientStorage(new \Xibo\Storage\ApiClientStorage());
$server->setScopeStorage(new \Xibo\Storage\ApiScopeStorage());
$server->setAuthCodeStorage(new \Xibo\Storage\ApiAuthCodeStorage());

// Allow auth code grant
$authCodeGrant = new \League\OAuth2\Server\Grant\AuthCodeGrant();
$server->addGrantType($authCodeGrant);

// Allow client credentials grant
$clientCredentialsGrant = new \League\OAuth2\Server\Grant\ClientCredentialsGrant();
$server->addGrantType($clientCredentialsGrant);

// Add refresh tokens
$refreshTokenGrant = new \League\OAuth2\Server\Grant\RefreshTokenGrant();
$server->addGrantType($refreshTokenGrant);

// DI in the server
$app->server = $server;

// Auth Routes
$app->get('/', function() use ($app) {

    $authParams = $app->server->getGrantType('authorization_code')->checkAuthorizeParams();

    // Redirect the user to the UI - save the auth params in the session.
    $app->session->set('authParams', $authParams);

    // We know we are at /api/authorize, so convert that to /application/authorise
    $app->redirect(str_replace('/api/authorize/', '/application/authorize', $app->request()->getPath()));

})->name('home');

// Access Token
$app->post('/access_token', function() use ($app) {

    \Xibo\Helper\Log::debug('Request for access token using grant_type: %s', $app->request()->post('grant_type'));

    $token = json_encode($app->server->issueAccessToken());

    \Xibo\Helper\Log::debug('Issued token: %s', $token);

    // Issue an access token
    $app->halt(200, $token);

    // Exceptions are caught by our error controller automatically.
});

// Run app
$app->run();