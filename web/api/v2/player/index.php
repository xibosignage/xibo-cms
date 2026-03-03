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
use Xibo\Factory\ContainerFactory;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../../../..'));

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

// Check that the cache folder if writeable
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
    $logger = new Logger('PLAYER-API-V2');

    $logger->pushProcessor(new UidProcessor());
    $logger->pushHandler(new \Xibo\Helper\DatabaseLogHandler());

    return $logger;
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));

// Config
$container->get('configService');
$container->set('name', 'PLAYER-API-V2');

// Register the PlayerApiV2 controller in the DI container.
// Self-contained — no need to modify lib/Dependencies/Controllers.php.
$container->set('\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', function (ContainerInterface $c) {
    $controller = new \Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2(
        $c->get('displayFactory'),
        $c,
    );
    $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
    return $controller;
});

// Middleware (minimal stack — no auth, no CSRF, no themes, no Twig)
$app->add(new \Xibo\Middleware\ConnectorMiddleware($app));
$app->add(new \Xibo\Middleware\ListenersMiddleware($app));
$app->add(new \Xibo\Middleware\Log($app));
$app->add(new \Xibo\Middleware\State($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->add(new \Xibo\Middleware\Xmr($app));
$app->addRoutingMiddleware();
$app->add(new \Xibo\Middleware\TrailingSlashMiddleware($app));

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(
    \Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging(),
    true,
    true
);
$errorMiddleware->setDefaultErrorHandler(\Xibo\Middleware\Handlers::jsonErrorHandler($container));

// ─── Public routes (no JWT required) ────────────────────────────

$app->get('/health', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'health'])->setName('player.v2.health');
$app->post('/auth', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'auth'])->setName('player.v2.auth');

// ─── Protected routes (JWT required) ────────────────────────────
// Grouped under PlayerAuthMiddleware which validates Bearer tokens

$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Display management
    $group->post('/displays', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'registerDisplay'])
        ->setName('player.v2.displays.register');
    $group->get('/displays/{id}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getDisplay'])
        ->setName('player.v2.displays.get');
    $group->put('/displays/{id}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'updateDisplay'])
        ->setName('player.v2.displays.update');

    // Media & schedule
    $group->get('/displays/{id}/media', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getRequiredMedia'])
        ->setName('player.v2.displays.media');
    $group->get('/displays/{id}/schedule', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getSchedule'])
        ->setName('player.v2.displays.schedule');

    // Data & resources
    $group->get('/datasets/{id}/data', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getDatasetData'])
        ->setName('player.v2.datasets.data');
    $group->get('/widgets/{id}/resource', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getWidgetResource'])
        ->setName('player.v2.widgets.resource');
    $group->get('/widgets/{layoutId}/{regionId}/{mediaId}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getWidgetResourceByPath'])
        ->setName('player.v2.widgets.resource.bypath');
    $group->get('/media/{id}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'downloadMedia'])
        ->setName('player.v2.media.download');
    $group->get('/media/file/{storedAs:.+}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'downloadMediaByFilename'])
        ->setName('player.v2.media.download.byfilename');
    $group->get('/data/{id}.json', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getDatasetData'])
        ->setName('player.v2.data.json');
    $group->get('/layouts/{id}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'downloadLayout'])
        ->setName('player.v2.layouts.download');
    $group->get('/dependencies/{filename:.+}', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'downloadDependency'])
        ->setName('player.v2.dependencies.download');
    $group->get('/displays/{id}/weather', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'getWeather'])
        ->setName('player.v2.displays.weather');

    // Reporting
    $group->put('/displays/{id}/status', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'updateStatus'])
        ->setName('player.v2.displays.status');
    $group->post('/displays/{id}/logs', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'submitLogs'])
        ->setName('player.v2.displays.logs');
    $group->post('/displays/{id}/stats', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'submitStats'])
        ->setName('player.v2.displays.stats');
    $group->post('/displays/{id}/screenshot', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'submitScreenshot'])
        ->setName('player.v2.displays.screenshot');
    $group->put('/displays/{id}/inventory', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'updateInventory'])
        ->setName('player.v2.displays.inventory');
    $group->post('/displays/{id}/faults', ['\Xibo\Custom\PlayerApiV2\Controller\PlayerApiV2', 'reportFaults'])
        ->setName('player.v2.displays.faults');
})->add(new \Xibo\Custom\PlayerApiV2\Middleware\PlayerAuthMiddleware($container));

// Run App
try {
    $app->run();
} catch (Exception $e) {
    echo 'Fatal Error - sorry this shouldn\'t happen. ';
    echo '<br>' . $e->getMessage();

    if (ini_get('display_errors') == 1) {
        echo '<br><br><code>' . nl2br($e->getTraceAsString()) . '</code>';
    }
}
