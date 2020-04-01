<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

error_reporting(0);
ini_set('display_errors', 0);

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
$app->setBasePath(\Xibo\Middleware\State::determineBasePath());

// Config
$app->config = $container->get('configService');
$routeParser = $app->getRouteCollector()->getRouteParser();
$container->set('name', 'API');

$app->add(new \Xibo\Middleware\ApiAuthenticationOAuth($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->add(new \Xibo\Middleware\State($app));
$app->add(new \Xibo\Middleware\Log($app));
$app->add(new \Xibo\Middleware\Xmr($app));

$app->addRoutingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(\Xibo\Middleware\Handlers::jsonErrorHandler($container));

// Handle additional Middleware
\Xibo\Middleware\State::setMiddleWare($app);

// All routes
require PROJECT_ROOT . '/lib/routes.php';

$app->get('/', ['\Xibo\Controller\Login','About']);
$app->post('/library/mcaas/{id}', ['\Xibo\Controller\Library','mcaas']);

// Run app
$app->run();