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

// Is the WSDL being requested.
if (isset($_GET['wsdl']))
{
    $wsdl = file_get_contents('lib/service/service.wsdl');
    echo $wsdl;
    exit;
}

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
                $soap = new SoapServer('http://localhost/Series%201.1/111-server/server/lib/service/service.wsdl');
                $soap->setClass('XMDSSoap');
                $soap->handle();
            }
            catch (Exception $e)
            {
                echo 'Unable to create a SOAP server';
                exit;
            }

            break;

        default:
            echo 'Not implemented';
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