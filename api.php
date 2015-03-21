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
require 'lib/app/debug.class.php';
require 'config/config.class.php';
require 'lib/app/translationengine.class.php';
require 'lib/app/session.class.php';
require 'modules/module_user_general.php';
// END

if (!file_exists('settings.php'))
    die('Not configured');

error_reporting(E_ALL);
ini_set('display_errors', 1);

Config::Load();
new Debug();

$app = new \Slim\Slim(array(
    'debug' => true
));
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
$user = new \User();
$app->container->singleton('user', function() use ($user) { return $user; });

// Once we have a user, initialise the theme
new \Xibo\Helper\Theme($app->user);

$app->get('/layouts', function() use ($app) {
    $controller = new \Xibo\Controller\Layout();
    $controller->setApp($app);
    $controller->LayoutGrid();

    $app->render(200, $app->state);
})->name('layoutSearch');

$app->get('/layouts/:id', function($id) use ($app) {
    $app->render(200, array('layout' => \Xibo\Factory\LayoutFactory::getById($id)));
})->name('layoutGet');

$app->post('/layouts/:id', function($id) use ($app) {
    // Update the Layout
})->name('layoutUpdate');

$app->run();