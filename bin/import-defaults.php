<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

/**
 * Imports the stock layouts and default fonts on a new install, before the web server starts.
 * Usage: import-defaults.php [brand folder]; exits 0 once imported, 3 when already imported and 1 on failure.
 *
 * @phpcs:disable PSR1.Files.SideEffects
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Views\TwigMiddleware;
use Xibo\Factory\ContainerFactory;
use Xibo\Helper\DatabaseLogHandler;
use Xibo\Service\DefaultLayoutBuilder;
use Xibo\Service\DefaultsImporter;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    fwrite(STDERR, 'Not configured' . PHP_EOL);
    exit(1);
}

$failure = null;
$isImported = false;
$brandFolder = $argv[1] ?? null;

try {
    $container = ContainerFactory::create();
    // Runs as the task runner does, as the system user.
    $container->set('name', 'xtr');
    $container->set('logger', function () {
        $logger = new Logger('DEFAULTS');
        $logger->pushProcessor(new UidProcessor());
        $logger->pushHandler(new DatabaseLogHandler());
        $logger->pushHandler(new StreamHandler(STDERR, Logger::NOTICE));
        return $logger;
    });

    $app = \DI\Bridge\Slim\Bridge::create($container);
    $app->setBasePath($container->get('basePath'));
    $app->configService = $container->get('configService');

    if (\Xibo\Helper\Environment::migrationPending()) {
        throw new \RuntimeException('Upgrade pending');
    }

    // The same middleware as bin/run.php, so the layout import has its listeners and user.
    \Xibo\Middleware\State::setMiddleWare($app);
    $app->add(new \Xibo\Middleware\ConnectorMiddleware($app));
    $app->add(new \Xibo\Middleware\ListenersMiddleware($app));
    $app->add(new \Xibo\Middleware\Storage($app));
    $app->add(new \Xibo\Middleware\Xtr($app));
    $app->add(new \Xibo\Middleware\State($app));
    $app->add(TwigMiddleware::createFromContainer($app));
    $app->add(new \Xibo\Middleware\Log($app));
    $app->add(new \Xibo\Middleware\Xmr($app));
    $app->addRoutingMiddleware();

    $app->get('/', function (
        Request $request,
        ResponseInterface $response
    ) use (
        $container,
        $brandFolder,
        &$failure,
        &$isImported
    ) {
        $config = $container->get('configService');
        $store = $container->get('store');
        $importer = new DefaultsImporter(
            $config,
            $store,
            $container->get('layoutFactory'),
            $container->get('userFactory'),
            $container->get('dataSetFactory'),
            $container->get('fontFactory'),
            $container->get('mediaService'),
            new DefaultLayoutBuilder(
                $config,
                $container->get('layoutFactory'),
                $container->get('playlistFactory'),
                $container->get('widgetFactory'),
                $container->get('moduleFactory'),
                $container->get('mediaFactory'),
                $container->get('logger')
            ),
            $container->get('logger')
        );

        try {
            if ($importer->isRequired()) {
                $importer->import(
                    $brandFolder ?? rtrim($config->getSetting('LIBRARY_LOCATION'), '/') . '/brand'
                );
                $isImported = true;
            }
            $store->commitIfNecessary();
        } catch (\Throwable $exception) {
            $store->rollBackIfNecessary();
            $failure = 'Defaults could not be imported: ' . $exception->getMessage();
        }

        return $response;
    });

    $app->handle(new Request(new ServerRequest('GET', '/')));
} catch (\Throwable $exception) {
    $failure = 'Defaults could not be imported: ' . $exception->getMessage();
}

if ($failure !== null) {
    fwrite(STDERR, $failure . PHP_EOL);
    exit(1);
}
exit($isImported ? 0 : 3);
