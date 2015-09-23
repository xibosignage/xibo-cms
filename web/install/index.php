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
use Xibo\Helper\Theme;
use Xibo\Helper\Translate;

DEFINE('XIBO', true);
DEFINE('MAX_EXECUTION', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

// Create a theme
new Theme('default');

// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'INSTALL',
    'handlers' => array(
        new \Monolog\Handler\StreamHandler(PROJECT_ROOT . '/install/install_log.txt')
    ),
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

// Installer is its own little Slim application
$app = new \Slim\Slim(array(
    'mode' => 'install',
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('install');

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    \Xibo\Helper\Log::critical('Unexpected Error: %s', $e->getMessage());
    \Xibo\Helper\Log::debug($e->getTraceAsString());

    $app->halt(500, 'Sorry there has been an unexpected error. ' . $e->getMessage());
});

// Configure a not found handler
$app->notFound(function () use ($app) {
    $controller = new \Xibo\Controller\Error();
    $controller->notFound();
});

// Twig templating
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => PROJECT_ROOT . '/cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new Twig_Extensions_Extension_I18n()
);

// Configure the template folder
$twig->twigTemplateDirs = [PROJECT_ROOT . '/views'];
$app->view($twig);

$twig->appendData(['theme' => Theme::getInstance()]);

// Hook to setup translations
$app->hook('slim.before.dispatch', function() use ($app) {

    if (file_exists(PROJECT_ROOT . '/web/settings.php')) {
        include_once(PROJECT_ROOT . '/web/settings.php');
        // Set-up the translations for get text
        Translate::InitLocale();

        $app->settingsExists = true;
    }
    else {
        Translate::InitLocale('en_GB');
    }

    \Xibo\Middleware\State::setRootUri($app);
});

require PROJECT_ROOT . '/lib/routes-install.php';

// Run App
$app->run();
