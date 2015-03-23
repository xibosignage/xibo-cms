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
DEFINE('XIBO', true);

require 'lib/autoload.php';
require 'vendor/autoload.php';

// Classes we need to deprecate
require 'lib/app/kit.class.php';
require 'config/config.class.php';
require 'lib/app/translationengine.class.php';
require 'lib/app/formmanager.class.php';
require 'lib/app/session.class.php';
require 'lib/data/data.class.php';
// END

if (!file_exists('settings.php'))
    die('Not configured');

error_reporting(E_ALL);
ini_set('display_errors', 1);

Config::Load();

// Setup the translations for gettext
TranslationEngine::InitLocale();

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
    'mode' => 'development',
    'log.writer' => $logger,
    'log.level' => \Slim\Log::DEBUG,
    'debug' => true
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
require 'lib/routes-web.php';
require 'lib/routes.php';

// Run App
$app->run();