<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\ContainerFactory;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../../..'));

error_reporting(1);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}

$container->set('logger', function () {
    $logger = new Logger('AUTH');

    $uidProcessor = new UidProcessor();
    // db
    $dbhandler = new \Xibo\Helper\DatabaseLogHandler();

    $logger->pushProcessor($uidProcessor);
    $logger->pushHandler($dbhandler);

    return $logger;
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));

// Config
$app->config = $container->get('configService');
$routeParser = $app->getRouteCollector()->getRouteParser();
$container->set('name', 'auth');

// Config
$app->add(new \Xibo\Middleware\ApiAuthentication($app));
$app->add(new \Xibo\Middleware\State($app));
$app->add(new \Xibo\Middleware\Log($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->addRoutingMiddleware();
$app->add(new \Xibo\Middleware\TrailingSlashMiddleware($app));

// Define Custom Error Handler
$errorMiddleware = $app->addErrorMiddleware(
    \Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging(),
    true,
    true
);
$errorMiddleware->setDefaultErrorHandler(\Xibo\Middleware\Handlers::jsonErrorHandler($container));

// Auth Routes
$app->get('/', function (Request $request, Response $response) use ($app) {
    /** @var \League\OAuth2\Server\AuthorizationServer $server */
    $server = $app->getContainer()->get('server');
    $authRequest = $server->validateAuthorizationRequest($request);

    // Redirect the user to the UI - save the auth params in the session.
    $app->getContainer()->get('session')->set('authParams', $authRequest);
    return $response->withRedirect(str_replace(
        '/api/authorize/',
        '/application/authorize',
        $request->getUri()->getPath()
    ));
})->setName('home');

// Access Token
$app->post('/access_token', function (Request $request, Response $response) use ($app) {
    $app->getContainer()->get('logService')->debug(sprintf(
        'Request for access token using grant_type: %s',
        $request->getParam('grant_type')
    ));
    $server = $app->getContainer()->get('server');

    // Try to respond to the request
    return $server->respondToAccessTokenRequest($request, $response);
});

// Run app
$app->run();
