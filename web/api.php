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

DEFINE('XIBO', true);
require 'lib/autoload.php';
require 'vendor/autoload.php';

// Classes we need to deprecate
require 'lib/app/kit.class.php';
require 'config/config.class.php';
require 'lib/app/translationengine.class.php';
require 'lib/app/session.class.php';
// END

if (!file_exists('settings.php'))
    die('Not configured');

Config::Load();

$app = new \Slim\Slim(array(
    'debug' => true
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

$app->view(new JsonApiView());
$app->add(new JsonApiMiddleware());

// The current user
// this should be injected by the ApiAuthenticationOAuth middleware
$app->user = \Xibo\Factory\UserFactory::getById(1);

// All routes
require 'lib/routes.php';

// Run app
$app->run();