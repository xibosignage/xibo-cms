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
        $file = \Xibo\Factory\XmdsNonceFactory::getByNonce($_REQUEST['file']);
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
    $wsdl = PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl';

    if (!file_exists($wsdl))
        throw new InvalidArgumentException('Your client is not the correct version to communicate with this CMS.');

    // Initialise a theme
    new \Xibo\Helper\Theme();

    // Create a SoapServer
    $soap = new SoapServer($wsdl);
    //$soap = new SoapServer($wsdl, array('cache_wsdl' => WSDL_CACHE_NONE));
    $soap->setClass('Xibo\Xmds\Soap' . $version);
    $soap->handle();
}
catch (Exception $e) {
    Log::error($e->getMessage());

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    die ($e->getMessage());
}