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

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

// convert all the command line arguments into a URL
$argv = $GLOBALS['argv'];
array_shift($GLOBALS['argv']);
$pathInfo = '/' . implode('/', $argv);

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'CONSOLE',
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
$app->setName('console');

// Config
$app->configService = \Xibo\Service\ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

// Check for upgrade after we've loaded settings to make sure the main app gets any custom settings it needs.
if (\Xibo\Helper\Environment::migrationPending()) {
    die('Upgrade pending');
}

// Set up the environment so that Slim can route
$app->environment = Slim\Environment::mock([
    'PATH_INFO'   => $pathInfo
]);

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

\Xibo\Middleware\Storage::setStorage($app->container);
\Xibo\Middleware\State::setState($app);

$app->add(new \Xibo\Middleware\Xtr());
$app->add(new \Xibo\Middleware\Storage());
$app->add(new \Xibo\Middleware\Xmr());

// Handle additional Middleware
\Xibo\Middleware\State::setMiddleWare($app);

// Configure a user
$app->user = $app->userFactory->getSystemUser();

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $app->container->get('\Xibo\Controller\Error')->handler($e);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $app->container->get('\Xibo\Controller\Error')->notFound();
});

// All routes
$app->get('/', '\Xibo\Controller\Task:poll');
$app->get('/:id', '\Xibo\Controller\Task:run');

// Run app
$app->run();

