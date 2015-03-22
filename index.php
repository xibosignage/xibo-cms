<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (index.php) is part of Xibo.
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
require 'lib/app/formmanager.class.php';
require 'lib/app/session.class.php';
require 'lib/data/data.class.php';
// END

if (!file_exists('settings.php'))
    die('Not configured');

error_reporting(E_ALL);
ini_set('display_errors', 1);

Config::Load();

// Setup the translations for gettext
TranslationEngine::InitLocale();

// Create a logger
$logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
    'handlers' => array(
        new \Monolog\Handler\ChromePHPHandler()
    ),
    'processors' => array(
        new \Xibo\Helper\RouteProcessor()
    )
));

// Slim Application
$app = new \Slim\Slim(array(
    'log.writer' => $logger,
    'log.level' => \Slim\Log::DEBUG,
    'debug' => true
));
$app->setName('web');

// Middleware
$app->add(new \Xibo\Middleware\Storage());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Actions());
$app->add(new \Xibo\Middleware\CsrfGuard());
$app->add(new \Xibo\Middleware\WebAuthentication());

// web view
$app->view(new \Xibo\Middleware\WebView());

// Special "root" route
$app->get('/', function () use ($app) {
    // Different controller depending on the homepage of the user.

    $controller = new \Xibo\Controller\Layout($app);
    $controller->displayPage();
    $controller->render();

})->setName('home');

// Special "login" route
$app->get('/login', function () use ($app) {
    // Login form
    $controller = new \Xibo\Controller\Login($app);
    $controller->setNotAutomaticFullPage();
    $controller->render('loginForm');

})->setName('login');

// POST Login
$app->post('/login', function () use ($app) {

    // Capture the prior route (if there is one)
    $priorRoute = ($app->request()->post('priorPage'));

    try {
        $controller = new \Xibo\Controller\Login($app);
        $controller->login();
    }
    catch (\Xibo\Exception\AccessDeniedException $e) {
        $app->flash('login_message', __('Username or Password incorrect'));
        $app->flash('priorRoute', $priorRoute);
        $app->redirectTo('login');
    }
    catch (\Xibo\Exception\FormExpiredException $e) {
        $app->flash('priorRoute', $priorRoute);
        $app->redirectTo('login');
    }

    \Xibo\Helper\Log::info('%s user logged in.', $app->user->userName);

    $app->redirect($app->request->getRootUri() . (($priorRoute == '' || stripos($priorRoute, 'login')) ? '' : $priorRoute));
});

$app->get('/about', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->render('About');

})->setName('about');

// Ping pong route
$app->get('/login/ping', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->PingPong();
    $controller->render();
})->setName('ping');

$app->get('/layout/view', function () use ($app) {
    // This is a full page
    $controller = new \Xibo\Controller\Layout($app);
    $controller->displayPage();
    $controller->render();
});

$app->get('/layout/add', function () use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->AddForm();
    $controller->render();
})->setName('layoutAddForm');

$app->post('/ExchangeGridTokenForFormToken', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->ExchangeGridTokenForFormToken();
    $controller->render();
});

// All application routes
require 'routes.php';

// Run App
$app->run();