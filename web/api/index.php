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
DEFINE('RELATIVE_URL_BASE', '../../');

error_reporting(0);
ini_set('display_errors', 0);

require '../../lib/autoload.php';
require '../../vendor/autoload.php';

if (!file_exists('../settings.php'))
    die('Not configured');

Config::Load('../settings.php');

// Create a logger
$logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
    'handlers' => array(
        new \Xibo\Helper\DatabaseLogHandler()
    ),
    'processors' => array(
        new \Xibo\Helper\RouteProcessor()
    )
));

$app = new \Slim\Slim(array(
    'log.writer' => $logger
));
$app->setName('api');
$app->add(new \Xibo\Middleware\Storage());
$app->add(new \Xibo\Middleware\State());

// oAuth Resource
/*$sessionStorage = new Storage\SessionStorage();
$accessTokenStorage = new Storage\AccessTokenStorage();
$clientStorage = new Storage\ClientStorage();
$scopeStorage = new Storage\ScopeStorage();

$server = new \League\OAuth2\Server\ResourceServer(
    $sessionStorage,
    $accessTokenStorage,
    $clientStorage,
    $scopeStorage
);

$app->add(new \Xibo\Middleware\ApiAuthenticationOAuth($server));*/

$app->add(new JsonApiMiddleware());
$app->view(new JsonApiView());

// The current user
// this should be injected by the ApiAuthenticationOAuth middleware
$app->user = \Xibo\Factory\UserFactory::getById(1);

// All routes
require '../../lib/routes.php';

// Run app
$app->run();