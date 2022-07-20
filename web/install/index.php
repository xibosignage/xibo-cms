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

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Slim\Views\TwigMiddleware;
use Xibo\Factory\ContainerFactory;
use Xibo\Helper\Translate;

DEFINE('XIBO', true);
DEFINE('MAX_EXECUTION', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}
$container->set('name', 'install');

$container->set('logger', function () {
    $logger = new Logger('INSTALL');
    $handler = new StreamHandler(PROJECT_ROOT . '/library/install_log.txt', Logger::DEBUG);

    $uidProcessor = new UidProcessor();
    $logger->pushProcessor($uidProcessor);
    $logger->pushHandler($handler);

    return $logger;
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));
// Config
$app->add(TwigMiddleware::createFromContainer($app));
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Create an empty config object
$emptyConfigService = new \Xibo\Service\ConfigService();
$emptyConfigService->loadTheme('default');

$basePath = str_replace('/install', '', $app->getBasePath());
$emptyConfigService->rootUri = $basePath;

/** @var \Slim\Views\Twig $twig */
$twig = $container->get('view');
$twig->offsetSet('theme', $emptyConfigService);
$app->getContainer()->set('configService', $emptyConfigService);

if (file_exists(PROJECT_ROOT . '/web/settings.php')) {
    // Populate our new DB info
    require(PROJECT_ROOT . '/web/settings.php');
    \Xibo\Service\ConfigService::$dbConfig = [
        'host' => $dbhost,
        'user' => $dbuser,
        'password' => $dbpass,
        'name' => $dbname,
        'ssl' => $dbssl ?? null,
        'sslVerify' => $dbsslverify ?? null
    ];

    // Set-up the translations for get text
    $app->getContainer()->get('configService')->setDependencies($app->getContainer()->get('store'), $app->getBasePath());
    Translate::InitLocale($app->getContainer()->get('configService'));
}
else {
    Translate::InitLocale($app->getContainer()->get('configService'), 'en_GB');
}

require PROJECT_ROOT . '/lib/routes-install.php';

// Run App
$app->run();
