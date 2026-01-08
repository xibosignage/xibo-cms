<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Psr\Container\ContainerInterface;
use Slim\Views\TwigMiddleware;
use Xibo\Factory\ContainerFactory;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

require PROJECT_ROOT . '/vendor/autoload.php';

// Enable/Disable logging
if (\Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Should we show the installer?
if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

// Check that the cache folder if writeable - if it isn't we're in big trouble
if (!is_writable(PROJECT_ROOT . '/cache')) {
    die('Installation Error: Cannot write files into the Cache Folder');
}

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}

// Configure Monolog
$container->set('logger', function (ContainerInterface $container) {
    $logger = new Logger('PREVIEW');

    $logger->pushProcessor(new UidProcessor());
    $logger->pushHandler(new \Xibo\Helper\DatabaseLogHandler());

    return $logger;
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));

// Config
$container->get('configService');
$container->set('name', 'PREVIEW');

// Middleware
$app->add(new \Xibo\Middleware\Theme($app));
$app->add(new \Xibo\Middleware\ConnectorMiddleware($app));
$app->add(new \Xibo\Middleware\ListenersMiddleware($app));
$app->add(new \Xibo\Middleware\Log($app));
$app->add(new \Xibo\Middleware\State($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->add(new \Xibo\Middleware\Xmr($app));
$app->add(new \Xibo\Middleware\Csp($app->getContainer(), false));
$app->add(TwigMiddleware::createFromContainer($app));
$app->addRoutingMiddleware();
$app->add(new \Xibo\Middleware\TrailingSlashMiddleware($app));
$app->add(new \Xibo\Middleware\CorsPreviewMiddleware());

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(
    \Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging(),
    true,
    true
);
$errorMiddleware->setDefaultErrorHandler(\Xibo\Middleware\Handlers::webErrorHandler($container));

// Application routes
// ------------------
// CORS
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Private ones
$app->group('/', function () use ($app) {
    $app->get('/layout/preview/{id}', ['\Xibo\Controller\Preview', 'show'])
        ->setName('layout.preview');
    $app->get('/layout/xlf/{id}', ['\Xibo\Controller\Preview', 'getXlf'])
        ->setName('layout.getXlf');
    $app->get('/playlist/widget/resource/{regionId}[/{id}]', ['\Xibo\Controller\Widget', 'getResource'])
        ->setName('module.getResource');
    $app->get('/playlist/widget/data/{regionId}/{id}', ['\Xibo\Controller\Widget', 'getData'])
        ->setName('module.getData');
    $app->get('/fonts/fontcss', ['\Xibo\Controller\Font','fontCss'])
        ->setName('library.font.css');
    $app->get('/fonts/download/{id}', ['\Xibo\Controller\Font', 'download'])
        ->setName('font.download');
    $app->get('/layout/background/{id}', ['\Xibo\Controller\Layout', 'downloadBackground'])
        ->setName('layout.download.background');
    $app->get('/library/download/{id}', ['\Xibo\Controller\Library', 'download'])
        ->setName('library.download');
    $app->get('/library/thumbnail/{id}', ['\Xibo\Controller\Library', 'thumbnail'])
        ->setName('library.public.thumbnail');
})->addMiddleware(new \Xibo\Middleware\TokenAuthMiddleware($app->getContainer()));

// Public ones
$app->group('/', function () use ($app) {
    $app->get('/layout/playerBundle', ['\Xibo\Controller\Preview', 'playerBundle'])
        ->setName('layout.preview.bundle');
    $app->get('/module/asset/{assetId}', ['\Xibo\Controller\Module', 'assetDownload'])
        ->setName('module.asset.download');
    $app->get('/connector/widget/preview', ['\Xibo\Controller\Connector', 'connectorPreview'])
        ->setName('layout.preview.connector');
});

// Run App
try {
    $app->run();
} catch (Exception $e) {
    echo 'Fatal Error - sorry this shouldn\'t happen. ';
    echo '<br>' . $e->getMessage();

    // Only output debug trace if we're configured to display errors
    if (ini_get('display_errors') == 1) {
        echo '<br><br><code>' . nl2br($e->getTraceAsString()) . '</code>';
    }
}
