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
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../lib/autoload.php';
require '../vendor/autoload.php';

// Classes we need to deprecate, namespace or put in composer
require '../lib/app/kit.class.php';
require '../lib/data/data.class.php';
// END

if (!file_exists('settings.php'))
    die('Not configured');

// Load the config
Config::Load();

// Create a logger
$logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
    'handlers' => array(
        new \Monolog\Handler\ChromePHPHandler(),
        new \Xibo\Helper\DatabaseLogHandler()
    ),
    'processors' => array(
        new \Xibo\Helper\RouteProcessor()
    )
));

// Slim Application
$app = new \Slim\Slim(array(
    'mode' => Config::GetSetting('SERVER_MODE'),
    'log.writer' => $logger
));
$app->setName('web');

// web view
$app->view(new \Xibo\Middleware\WebView());

// Middleware
$app->add(new \Xibo\Middleware\Storage());
$app->add(new \Xibo\Middleware\State());
$app->add(new \Xibo\Middleware\Actions());
$app->add(new \Xibo\Middleware\CsrfGuard());
$app->add(new \Xibo\Middleware\WebAuthentication());

// All application routes
require '../lib/routes-web.php';
require '../lib/routes.php';

// Run App
$app->run();