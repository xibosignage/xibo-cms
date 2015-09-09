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
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

Config::Load(PROJECT_ROOT . '/web/settings.php');

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'API',
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
$app->setName('api');

$app->add(new \Xibo\Middleware\ApiAuthenticationOAuth());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Storage());
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
$sessionStorage = new \Xibo\Storage\ApiSessionStorage();
$accessTokenStorage = new \Xibo\Storage\ApiAccessTokenStorage();
$clientStorage = new \Xibo\Storage\ApiClientStorage();
$scopeStorage = new \Xibo\Storage\ApiScopeStorage();

$server = new \League\OAuth2\Server\ResourceServer(
    $sessionStorage,
    $accessTokenStorage,
    $clientStorage,
    $scopeStorage
);

// DI in the server
$app->server = $server;

// All routes
require PROJECT_ROOT . '/lib/routes.php';

$app->get('/', '\Xibo\Controller\Login:About');

// Run app
$app->run();