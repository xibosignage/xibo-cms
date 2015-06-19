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
use Xibo\Helper\Config;

DEFINE('XIBO', true);
DEFINE('RELATIVE_URL_BASE', '../');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

if (!file_exists('settings.php'))
    die('Not configured');

// Load the config
Config::Load('settings.php');

// Create a logger
$logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
    'name' => 'WEB',
    'handlers' => array(
        new \Monolog\Handler\ChromePHPHandler(\Monolog\Logger::INFO),
        new \Xibo\Helper\DatabaseLogHandler()
    ),
    'processors' => array(
        new \Xibo\Helper\LogProcessor()
    )
));

// Slim Application
$app = new \Slim\Slim(array(
    'mode' => Config::GetSetting('SERVER_MODE'),
    'log.writer' => $logger
));
$app->setName('web');
$app->runNo = \Xibo\Helper\Random::generateString(10);

// Twig templating
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => '../cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new Twig_Extensions_Extension_I18n()
);

// Configure the template folder
$twig->twigTemplateDirs = array_merge(\Xibo\Factory\ModuleFactory::getViewPaths(), ['../views']);

$app->view($twig);

// Middleware (onion, outside inwards and then out again - i.e. the last one is first and last)
$app->add(new \Xibo\Middleware\WebAuthentication());
$app->add(new \Xibo\Middleware\CsrfGuard());
$app->add(new \Xibo\Middleware\Theme());
$app->add(new \Xibo\Middleware\Actions());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Storage());

// All application routes
require '../lib/routes-web.php';
require '../lib/routes.php';

// Run App
$app->run();