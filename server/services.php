<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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

$method     = Kit::GetParam('method', _GET, _WORD, '');
$service    = Kit::GetParam('service', _GET, _WORD, 'soap');
$response   = Kit::GetParam('response', _GET, _WORD, 'xml');

// Work out the location of the services.
$serviceLocation = Kit::GetXiboRoot();

$serviceResponse = new XiboServiceResponse();

// Is the WSDL being requested.
if (isset($_GET['wsdl']))
    $serviceResponse->WSDL();

// Is the XRDS being requested
if (isset($_GET['xrds']))
    $serviceResponse->XRDS();

// Check to see if we are going to consume a service (if we came from xmds.php then we will always use the SOAP service)
if (defined('XMDS') || $method != '')
{
    // Create a service to handle the method
    switch ($service)
    {
        case 'soap':

            Kit::ClassLoader('xmdssoap');

            try
            {
                $soap = new SoapServer($serviceLocation . '?wsdl');
                $soap->setClass('XMDSSoap');
                $soap->handle();
            }
            catch (Exception $e)
            {
                $serviceResponse->ErrorServerError('Unable to create SOAP Server');
            }

            break;

        case 'oauth':

            Kit::ClassLoader('ServiceOAuth');

            $oauth = new ServiceOAuth();

            if (method_exists($oauth, $method))
                $oauth->$method();
            else
                $serviceResponse->ErrorServerError('Unknown Request.');

            break;

        case 'rest':
            $authorized = false;
            $oauthServer = new OAuthServer();

            try
            {
                if ($oauthServer->verifyIfSigned())
                    $authourized = true;
            }
            catch (OauthException $e)
            {

            }

            // Was authorization successful?
            if (!$authorized)
                $serviceResponse->ErrorServerError('OAuth Verification Failed: ' . $e->getMessage());
                
            // Authenticated with OAuth.

            // Detect response type requested.
            switch ($response)
            {
                case 'json':
                    Kit::ClassLoader('RESTJson');

                    $rest = new RESTJson();

                    break;

                case 'xml':
                    Kit::ClassLoader('RESTXml');

                    $rest = new RESTXml();

                    break;

                default:
                    $serviceResponse->ErrorServerError('Unknown response type');
            }

            if (method_exists($rest, $method))
                $rest->$method();
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