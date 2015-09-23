<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (xmds.php) is part of Xibo.
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
use Xibo\Helper\Log;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

// Load the config
Config::Load(PROJECT_ROOT . '/web/settings.php');

// Always have a version defined
$version = \Xibo\Helper\Sanitize::getInt('v', 3, $_REQUEST);

// Version Request?
if (isset($_GET['what']))
    die(Config::Version('XmdsVersion'));

// Is the WSDL being requested.
if (isset($_GET['wsdl']) || isset($_GET['WSDL'])) {
    $wsdl = new \Xibo\Xmds\Wsdl(PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl', $version);
    $wsdl->output();
    exit;
}

// We create a Slim Object ONLY for logging
// Create a logger
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'XMDS',
    'handlers' => array(
        new \Xibo\Helper\DatabaseLogHandler()
    ),
    'processors' => [
        new \Monolog\Processor\UidProcessor(7)
    ]
));

// Slim Application
$app = new \Slim\Slim(array(
    'mode' => Config::GetSetting('SERVER_MODE'),
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('api');

// Set state
\Xibo\Middleware\State::setState($app);

// We need a View for rendering GetResource Templates
// Twig templates
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => PROJECT_ROOT . '/cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new Twig_Extensions_Extension_I18n(),
    new \Xibo\Helper\UrlDecodeTwigExtension()
);

// Configure the template folder
$twig->twigTemplateDirs = array_merge(\Xibo\Factory\ModuleFactory::getViewPaths(), [PROJECT_ROOT . '/views']);
$app->view($twig);

// Configure a user
$app->user = \Xibo\Factory\UserFactory::getById(1);

// Check to see if we have a file attribute set (for HTTP file downloads)
if (isset($_GET['file'])) {
    // Check send file mode is enabled
    $sendFileMode = Config::GetSetting('SENDFILE_MODE');

    if ($sendFileMode == 'Off') {
        Log::notice('HTTP GetFile request received but SendFile Mode is Off. Issuing 404', 'services');
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // Check nonce, output appropriate headers, log bandwidth and stop.
    try {
        $file = \Xibo\Factory\RequiredFileFactory::getByNonce($_REQUEST['file']);
        $file->bytesRequested = $file->bytesRequested + $file->size;
        $file->isValid();

        // Issue magic packet
        // Send via Apache X-Sendfile header?
        if ($sendFileMode == 'Apache') {
            Log::notice('HTTP GetFile request redirecting to ' . Config::GetSetting('LIBRARY_LOCATION') . $file['storedAs'], 'services');
            header('X-Sendfile: ' . Config::GetSetting('LIBRARY_LOCATION') . $file['storedAs']);
        }
        // Send via Nginx X-Accel-Redirect?
        else if ($sendFileMode == 'Nginx') {
            header('X-Accel-Redirect: /download/' . $file['storedAs']);
        }
        else {
            header('HTTP/1.0 404 Not Found');
        }

        // Log bandwidth
        \Xibo\Factory\BandwidthFactory::createAndSave(4, $file['displayId'], $file['size']);
    }
    catch (\Exception $e) {
        if ($e instanceof \Xibo\Exception\NotFoundException || $e instanceof \Xibo\Exception\FormExpiredException) {
            Log::notice('HTTP GetFile request received but unable to find XMDS Nonce. Issuing 404', 'services');
            // 404
            header('HTTP/1.0 404 Not Found');
        }
        else
            throw $e;
    }

    exit;
}


try {
    \Xibo\Storage\PDOConnect::init()->beginTransaction();

    $wsdl = PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl';

    if (!file_exists($wsdl))
        throw new InvalidArgumentException('Your client is not the correct version to communicate with this CMS.');

    // Initialise a theme
    new \Xibo\Helper\Theme();

    // Create a SoapServer
    //$soap = new SoapServer($wsdl);
    $soap = new SoapServer($wsdl, array('cache_wsdl' => WSDL_CACHE_NONE));
    $soap->setClass('\Xibo\Xmds\Soap' . $version);
    $soap->handle();

    \Xibo\Storage\PDOConnect::init()->commit();
}
catch (Exception $e) {
    Log::error($e->getMessage());
    \Xibo\Storage\PDOConnect::init()->rollBack();

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    die ($e->getMessage());
}