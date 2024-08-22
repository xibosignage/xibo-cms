<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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


namespace Xibo\Xmds;


use Xibo\Helper\HttpsDetect;

class Wsdl
{
    private $path;
    private $version;

    /**
     * Wsdl
     * @param $path
     * @param $version
     */
    public function __construct($path, $version)
    {
        $this->path = $path;
        $this->version = $version;
    }

    public function output()
    {
        // We need to buffer the output so that we can send a Content-Length header with the WSDL
        ob_start();
        $wsdl = file_get_contents($this->path);
        $wsdl = str_replace('{{XMDS_LOCATION}}', $this->getRoot() . '?v=' . $this->version, $wsdl);
        echo $wsdl;

        // Get the contents of the buffer and work out its length
        $buffer = ob_get_contents();
        $length = strlen($buffer);

        // Output the headers
        header('Content-Type: text/xml; charset=ISO-8859-1\r\n');
        header('Content-Length: ' . $length);

        // Flush the buffer
        ob_end_flush();
    }

    /**
     * get Root url
     * @return string
     */
    public static function getRoot(): string
    {
        # Check REQUEST_URI is set. IIS doesn't set it, so we need to build it
        # Attribution:
        # Code snippet from http://support.ecenica.com/web-hosting/scripting/troubleshooting-scripting-errors/how-to-fix-server-request_uri-php-error-on-windows-iis/
        # Released under BSD License
        # Copyright (c) 2009, Ecenica Limited All rights reserved.
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
            if (isset($_SERVER['QUERY_STRING'])) {
                $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
        ## End Code Snippet

        $request = explode('?', htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'));

        return ((new HttpsDetect())->getUrl()) . '/' . ltrim($request[0], '/');
    }
}
