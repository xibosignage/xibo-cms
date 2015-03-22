<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2010 Daniel Garner
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
use Xibo\Entity\User;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;

DEFINE('XIBO', true);
include_once("lib/xmds.inc.php");

$method = \Kit::GetParam('method', _REQUEST, _WORD, '');
$service = \Kit::GetParam('service', _REQUEST, _WORD, 'rest');
$response = \Kit::GetParam('response', _REQUEST, _WORD, 'xml');
$serviceResponse = new XiboServiceResponse();

// Is the XRDS being requested
if (isset($_GET['xrds']))
    $serviceResponse->XRDS();

// We need a theme
new Theme(new User());

// Check to see if we are going to consume a service (if we came from xmds.php then we will always use the SOAP service)
if (defined('XMDS') || $method != '')
{
    // Create a service to handle the method
    switch ($service)
    {
        case 'oauth':

            Log::notice('OAuth Webservice call');

            \Kit::ClassLoader('ServiceOAuth');

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

                        \Kit::ClassLoader($userClass[0]);

                        // Create a user.
                        // We need to set up our user with an old style database object
                        $db = new database();

                        if (!$db->connect_db($dbhost, $dbuser, $dbpass))
                            die('Database connection problem.');

                        if (!$db->select_db($dbname))
                            die('Database connection problem.');

                        $user = new User($db);

                        // Log this user in.
                        if (!$user->setIdentity($userID))
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

            Log::notice('Authenticated API call for [' . $method . '] with a [' . $response . '] response. Issued by UserId: ' . $user->userId, 'Services');
                
            // Authenticated with OAuth.
            \Kit::ClassLoader('Rest');

            // Detect response type requested.
            switch ($response)
            {
                case 'json':
                    \Kit::ClassLoader('RestJson');
                    
                    $rest = new RestJson($user, $_REQUEST);

                    break;

                case 'xml':
                    \Kit::ClassLoader('RestXml');

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