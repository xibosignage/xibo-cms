<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Xibo\Factory\ContainerFactory;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../../..'));

error_reporting(1);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}

$container->set('logger', function () {
    $logger = new Logger('API');

    $uidProcessor = new UidProcessor();
    // db
    $dbhandler  =  new \Xibo\Helper\DatabaseLogHandler();

    $logger->pushProcessor($uidProcessor);
    $logger->pushHandler($dbhandler);

    return $logger;
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);

// Config
$app->config = $container->get('configService');
$routeParser = $app->getRouteCollector()->getRouteParser();
// Config
$app->add(new \Xibo\Middleware\Log($app));
$app->add(new \Xibo\Middleware\ApiAuthorizationOAuth($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->add(new \Xibo\Middleware\State($app));

$app->addRoutingMiddleware();
$app->setBasePath('/api/authorize');
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
/* Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $app->container->get('\Xibo\Controller\Error')->handler($e);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $app->container->get('\Xibo\Controller\Error')->notFound();
});

*/

// Auth Routes
$app->get('/', function(Request $request, Response $response) use ($app) {

    $authParams = $app->getContainer()->get('server')->getGrantType('authorization_code')->checkAuthorizeParams();

    // Redirect the user to the UI - save the auth params in the session.
    $app->getContainer()->get('session')->set('authParams', $authParams);

    // We know we are at /api/authorize, so convert that to /application/authorize
    return $response->withRedirect('/application/authorize');

})->setName('home');

// Access Token
$app->post('/access_token', function(Request $request, Response $response) use ($app) {

    $app->getContainer()->get('logService')->debug('Request for access token using grant_type: %s', $request->getParam('grant_type'));

    $token = $app->getContainer()->get('server')->issueAccessToken();

    $app->getContainer()->get('logService')->debug('Issued token: %s', $token);

    // Issue an access token
    return $response->withJson($token);

    // Exceptions are caught by our error controller automatically.
});

// Run app
$app->run();