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

use Xibo\Service\ConfigService;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../../..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

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

$app = new \RKA\Slim(array(
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('auth');

// Config
$app->configService = ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

$app->add(new \Xibo\Middleware\ApiAuthorizationOAuth());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Storage());
$app->view(new \Xibo\Middleware\ApiView());

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $app->container->get('\Xibo\Controller\Error')->handler($e);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $app->container->get('\Xibo\Controller\Error')->notFound();
});

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

    $app->logService->debug('Request for access token using grant_type: %s', $app->request()->post('grant_type'));

    $token = json_encode($app->server->issueAccessToken());

    $app->logService->debug('Issued token: %s', $token);

    // Issue an access token
    $app->response->header('Content-Type', 'application/json');
    $app->halt(200, $token);

    // Exceptions are caught by our error controller automatically.
});

// Run app
$app->run();