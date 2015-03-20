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
DEFINE('XIBO', true);
require 'lib/xmds.inc.php';

$version = \Kit::GetParam('v', _REQUEST, _INT, 3);
$serviceResponse = new XiboServiceResponse();

// Version Request?
if (isset($_GET['what']))
    die(Config::Version('XmdsVersion'));

// Is the WSDL being requested.
if (isset($_GET['wsdl']) || isset($_GET['WSDL']))
    $serviceResponse->WSDL($version);

// Check to see if we have a file attribute set (for HTTP file downloads)
if (isset($_GET['file'])) {
    // Check send file mode is enabled
    $sendFileMode = Config::GetSetting('SENDFILE_MODE');

    if ($sendFileMode == 'Off') {
        Debug::LogEntry('audit', 'HTTP GetFile request received but SendFile Mode is Off. Issuing 404', 'services');
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // Check nonce, output appropriate headers, log bandwidth and stop.
    $nonce = new Nonce();
    if (!$file = $nonce->Details(Kit::GetParam('file', _GET, _STRING))) {
        Debug::LogEntry('audit', 'HTTP GetFile request received but unable to find XMDS Nonce. Issuing 404', 'services');
        // 404
        header('HTTP/1.0 404 Not Found');
    }
    else {
        // Issue magic packet
        // Send via Apache X-Sendfile header?
        if ($sendFileMode == 'Apache') {
            Debug::LogEntry('audit', 'HTTP GetFile request redirecting to ' . Config::GetSetting('LIBRARY_LOCATION') . $file['storedAs'], 'services');
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
        $bandwidth = new Bandwidth();
        $bandwidth->Log($file['displayId'], 4, $file['size']);
    }
    exit;
}

// We need a theme
new Theme(new User());

try {
    $wsdl = 'lib/service/service_v' . $version . '.wsdl';

    if (!file_exists($wsdl)) {
        $serviceResponse->ErrorServerError('Your client is not the correct version to communicate with this CMS.');
    }

    $soap = new SoapServer($wsdl);
    //$soap = new SoapServer($wsdl, array('cache_wsdl' => WSDL_CACHE_NONE));
    $soap->setClass('XMDSSoap' . $version);
    $soap->handle();
}
catch (Exception $e) {
    Debug::LogEntry('error', $e->getMessage());
    $serviceResponse->ErrorServerError('Unable to create SOAP Server: ' . $e->getMessage());
}