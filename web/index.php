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
use Xibo\Helper\Config;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists('settings.php')) {
    if (file_exists(PROJECT_ROOT . '/web/install/index.php')) {
        header('Location: install/');
        exit();
    }
    else {
        die('Not configured');
    }
}

// Load the config
Config::Load('settings.php');

// Log handlers
$handlers = [new \Xibo\Helper\DatabaseLogHandler()];

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'WEB',
    'handlers' => $handlers,
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

// Slim Application
$app = new \Slim\Slim(array(
    'mode' => Config::GetSetting('SERVER_MODE'),
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('web');

// Twig templates
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => PROJECT_ROOT . '/cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new Twig_Extensions_Extension_I18n(),
    new \Xibo\Helper\ByteFormatterTwigExtension(),
    new \Xibo\Helper\UrlDecodeTwigExtension()
);

// Configure the template folder
$twig->twigTemplateDirs = array_merge(\Xibo\Factory\ModuleFactory::getViewPaths(), [PROJECT_ROOT . '/views']);

$app->view($twig);

// Middleware (onion, outside inwards and then out again - i.e. the last one is first and last);
if (Config::$middleware != null && is_array(Config::$middleware)) {
    foreach (Config::$middleware as $object) {
        $app->add($object);
    }
}

$app->add(new \Xibo\Middleware\Actions());

// Authentication middleware
if (Config::$authentication != null && Config::$authentication instanceof \Slim\Middleware)
    $app->add(Config::$authentication);
else
    $app->add(new \Xibo\Middleware\WebAuthentication());

// Standard Xibo middleware
$app->add(new \Xibo\Middleware\CsrfGuard());
$app->add(new \Xibo\Middleware\Theme());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Storage());

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

// All application routes
require PROJECT_ROOT . '/lib/routes-web.php';
require PROJECT_ROOT . '/lib/routes.php';

// Run App
$app->run();