<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2015 Spring Signage Ltd
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

use Xibo\Helper\Translate;

DEFINE('XIBO', true);
DEFINE('MAX_EXECUTION', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

// Create a theme
new \Xibo\Middleware\Theme('default');

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'INSTALL',
    'handlers' => array(
        new \Monolog\Handler\StreamHandler(PROJECT_ROOT . '/library/install_log.txt')
    ),
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

// Installer is its own little Slim application
$app = new \RKA\Slim(array(
    'mode' => 'install',
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('install');

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $app->render('install-error.twig', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    Translate::InitLocale($app->configService, 'en_GB');
    $app->render('install-error.twig', ['error' => __('Page not found'), 'trace' => __('Sorry this page cannot be found.')], 500);
});

// Twig templating
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => PROJECT_ROOT . '/cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Xibo\Twig\TransExtension()
);

// Configure the template folder
$twig->twigTemplateDirs = [PROJECT_ROOT . '/views'];
$app->view($twig);

// Set root URI
\Xibo\Middleware\State::setRootUri($app);

// Create an empty config object
$emptyConfigService = new \Xibo\Service\ConfigService();
$emptyConfigService->loadTheme('default');

// Set the config root Uri
$emptyConfigService->rootUri = $app->rootUri;

$twig->appendData(['theme' => $emptyConfigService]);

// Store this in our collection
$app->configService = $emptyConfigService;

// Hook to setup translations
$app->hook('slim.before.dispatch', function() use ($app) {

    if (file_exists(PROJECT_ROOT . '/web/settings.php')) {
        // Config
        $app->configService = \Xibo\Service\ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

        // Configure Store
        \Xibo\Middleware\Storage::setStorage($app->container);

        // Inject into Config Service
        $app->configService->setDependencies($app->store, $app->rootUri);

        // Set-up the translations for get text
        Translate::InitLocale($app->configService);

        $app->settingsExists = true;
    }
    else {
        Translate::InitLocale($app->configService, 'en_GB');

        $app->container->singleton('logService', function($container) {
            return new \Xibo\Service\LogService($container->log, $container->mode);
        });
    }

    // Register the sanitizer
    $app->container->singleton('sanitizerService', function($container) {
        $sanitizer = new \Xibo\Service\SanitizeService($container->dateService);
        $sanitizer->setRequest($container->request);
        return $sanitizer;
    });
});

require PROJECT_ROOT . '/lib/routes-install.php';

// Run App
$app->run();
