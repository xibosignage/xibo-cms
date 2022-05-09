<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
use Nyholm\Psr7\ServerRequest;
use Slim\Http\ServerRequest as Request;
use Slim\Views\TwigMiddleware;
use Xibo\Factory\ContainerFactory;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

// convert all the command line arguments into a URL
$argv = $GLOBALS['argv'];
array_shift($GLOBALS['argv']);
$pathInfo = '/' . implode('/', $argv);

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}
$container->set('name', 'xtr');

$container->set('logger', function () {
    $logger = new Logger('CONSOLE');

    $uidProcessor = new UidProcessor();
    // db
    $dbhandler  =  new \Xibo\Helper\DatabaseLogHandler();

    $logger->pushProcessor($uidProcessor);
    $logger->pushHandler($dbhandler);

    // Optionally allow console logging
    if (isset($_SERVER['LOG_TO_CONSOLE']) && $_SERVER['LOG_TO_CONSOLE']) {
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(STDERR, Logger::DEBUG));
    }

    return $logger;
});

$app = \DI\Bridge\Slim\Bridge::create($container);

$app->setBasePath($container->get('basePath'));
// Config
$app->configService = $container->get('configService');

// Check for upgrade after we've loaded settings to make sure the main app gets any custom settings it needs.
if (\Xibo\Helper\Environment::migrationPending()) {
    die('Upgrade pending');
}

// Handle additional Middleware
\Xibo\Middleware\State::setMiddleWare($app);

$twigMiddleware = TwigMiddleware::createFromContainer($app);

$app->add(new \Xibo\Middleware\ConnectorMiddleware($app));
$app->add(new \Xibo\Middleware\ListenersMiddleware($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->add(new \Xibo\Middleware\Xtr($app));
$app->add(new \Xibo\Middleware\State($app));
$app->add($twigMiddleware);
$app->add(new \Xibo\Middleware\Log($app));
$app->add(new \Xibo\Middleware\Xmr($app));

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(
    \Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging(),
    true,
    true
);
$errorMiddleware->setDefaultErrorHandler(\Xibo\Middleware\Handlers::jsonErrorHandler($container));

// All routes
$app->get('/', ['\Xibo\Controller\Task','poll']);
$app->get('/{id}', ['\Xibo\Controller\Task','run']);

// if we passed taskId in console
if (!empty($argv)) {
    $request = new Request(new ServerRequest('GET', $pathInfo));
    return $app->handle($request);
}

// Run app
$app->run();

