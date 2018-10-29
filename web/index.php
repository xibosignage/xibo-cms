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

use Xibo\Service\ConfigService;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

// Should we show the installer?
if (!file_exists('settings.php')) {
    // Check to see if the install app is available
    if (file_exists(PROJECT_ROOT . '/web/install/index.php')) {
        header('Location: install/');
        exit();
    } else {
        // We can't do anything here - no install app and no settings file.
        die('Not configured');
    }
}

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'WEB',
    'handlers' => [
        new \Xibo\Helper\DatabaseLogHandler()
    ],
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

// Slim Application
$app = new \RKA\Slim(array(
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
    new \Xibo\Twig\TransExtension(),
    new \Xibo\Twig\ByteFormatterTwigExtension(),
    new \Xibo\Twig\UrlDecodeTwigExtension(),
    new \Xibo\Twig\DateFormatTwigExtension()
);

// Configure the template folder
$twig->twigTemplateDirs = [PROJECT_ROOT . '/views'];

$app->view($twig);

// Config
$app->configService = ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

//
// Middleware (onion, outside inwards and then out again - i.e. the last one is first and last);
//
$app->add(new \Xibo\Middleware\Actions());

// Theme Middleware
$app->add(new \Xibo\Middleware\Theme());

// Authentication middleware
if ($app->configService->authentication != null && $app->configService->authentication instanceof \Slim\Middleware)
    $app->add($app->configService->authentication);
else
    $app->add(new \Xibo\Middleware\WebAuthentication());

// Standard Xibo middleware
$app->add(new \Xibo\Middleware\CsrfGuard());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Storage());
$app->add(new \Xibo\Middleware\Xmr());

// Handle additional Middleware
\Xibo\Middleware\State::setMiddleWare($app);
//
// End Middleware
//

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $app->container->get('\Xibo\Controller\Error')->handler($e);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $app->container->get('\Xibo\Controller\Error')->notFound();
});

// All application routes
require PROJECT_ROOT . '/lib/routes-web.php';
require PROJECT_ROOT . '/lib/routes.php';

// Run App
try {
    $app->run();
}
catch (Exception $e) {
    echo 'Fatal Error - sorry this shouldn\'t happen. ';
    echo $e->getMessage();
}
