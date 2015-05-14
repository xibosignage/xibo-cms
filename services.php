<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
DEFINE('XIBO', true);
include_once("lib/xmds.inc.php");

$method = Kit::GetParam('method', _REQUEST, _WORD, '');
$service = Kit::GetParam('service', _REQUEST, _WORD, 'rest');
$response = Kit::GetParam('response', _REQUEST, _WORD, 'xml');
$version = Kit::GetParam('v', _REQUEST, _INT, 3);
$serviceResponse = new XiboServiceResponse();

// Version Request?
if (isset($_GET['what']))
    die(Config::Version('XmdsVersion'));

// Is the WSDL being requested.
if (isset($_GET['wsdl']) || isset($_GET['WSDL']))
    $serviceResponse->WSDL($version);

// Is the XRDS being requested
if (isset($_GET['xrds']))
    $serviceResponse->XRDS();

if (defined('XMDS'))
    $service = 'soap';

// We need a theme
new Theme(new User());

// Check to see if we are going to consume a service (if we came from xmds.php then we will always use the SOAP service)
if (defined('XMDS') || $method != '')
{
    // Create a service to handle the method
    switch ($service)
    {
        case 'soap':

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

                    // Debug
                    Debug::Audit('File request via magic packet. ' . $file['storedAs'], $file['displayId']);

                    // Log bandwidth
                    $bandwidth = new Bandwidth();
                    $bandwidth->Log($file['displayId'], 4, $file['size']);
                }
                exit;
            }

            try
            {
                $wsdl = 'lib/service/service_v' . $version . '.wsdl';

                if (!file_exists($wsdl)) {
                    $serviceResponse->ErrorServerError('Your client is not the correct version to communicate with this CMS.');
                }

                $soap = new SoapServer($wsdl);
                //$soap = new SoapServer($wsdl, array('cache_wsdl' => WSDL_CACHE_NONE));
                $soap->setClass('XMDSSoap' . $version);
                $soap->handle();
            }
            catch (Exception $e)
            {
                Debug::LogEntry('error', $e->getMessage());
                $serviceResponse->ErrorServerError('Unable to create SOAP Server: ' . $e->getMessage());
            }

            break;

        case 'oauth':

            Debug::LogEntry('audit', 'OAuth Webservice call');

            Kit::ClassLoader('ServiceOAuth');

            $oauth = new ServiceOAuth();

            if (method_exists($oauth, $method))
                $oauth->$method();
            else
                $serviceResponse->ErrorServerError('Unknown Request.');

            break;

        case 'rest':

            $serviceResponse->StartTransaction();

            // OAuth authorization.
            if (OAuthRequestVerifier::requestIsSigned())
            {
                try
                {
                    $request = new OAuthRequestVerifier();
                    $userID = $request->verify();

                    if ($userID)
                    {
                        // Create the login control system.
                        $userClass = Config::GetSetting('userModule');
                        $userClass = explode('.', $userClass);

                        Kit::ClassLoader($userClass[0]);

                        // Create a user.
                        // We need to set up our user with an old style database object
                        $db = new database();

                        if (!$db->connect_db($dbhost, $dbuser, $dbpass))
                            die('Database connection problem.');

                        if (!$db->select_db($dbname))
                            die('Database connection problem.');

                        $user = new User($db);

                        // Log this user in.
                        if (!$user->LoginServices($userID))
                        {
                            $serviceResponse->ErrorServerError('Unknown User.');
                        }
                    }
                    else
                    {
                        $serviceResponse->ErrorServerError('No user id.');
                    }
                }
                catch (OAuthException $e)
                {
                    $serviceResponse->ErrorServerError('Request signed but Unauthorized.');
                }
            }
            else
            {
                // Only signed requests allowed.
                $serviceResponse->ErrorServerError('Not signed.');
            }

            Debug::LogEntry('audit', 'Authenticated API call for [' . $method . '] with a [' . $response . '] response. Issued by UserId: ' . $user->userid, 'Services');
                
            // Authenticated with OAuth.
            Kit::ClassLoader('Rest');

            // Detect response type requested.
            switch ($response)
            {
                case 'json':
                    Kit::ClassLoader('RestJson');
                    
                    $rest = new RestJson($user, $_REQUEST);

                    break;

                case 'xml':
                    Kit::ClassLoader('RestXml');

                    $rest = new RestXml($user, $_REQUEST);

                    break;

                default:
                    $serviceResponse->ErrorServerError('Unknown response type');
            }

            // Run the method requested.
            if (method_exists($rest, $method))
                $serviceResponse->Success($rest->$method());
            else
                $serviceResponse->ErrorServerError('Unknown Method');

            break;

        default:
            $serviceResponse->ErrorServerError('Not implemented.');
    }
    exit;
}
// No method therefore output the XMDS landing page / document
?>
<html>
    <head>
        <title>Xmds</title>
    </head>
    <body>
        <h1>XMDS</h1>
    </body>
</html>