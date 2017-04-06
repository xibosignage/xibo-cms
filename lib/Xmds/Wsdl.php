<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Wsdl.php)
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
    public static function getRoot()
    {
        # Check REQUEST_URI is set. IIS doesn't set it so we need to build it
        # Attribution:
        # Code snippet from http://support.ecenica.com/web-hosting/scripting/troubleshooting-scripting-errors/how-to-fix-server-request_uri-php-error-on-windows-iis/
        # Released under BSD License
        # Copyright (c) 2009, Ecenica Limited All rights reserved.
        if (!isset($_SERVER['REQUEST_URI']))
        {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
            if (isset($_SERVER['QUERY_STRING']))
            {
                $_SERVER['REQUEST_URI'].='?'.$_SERVER['QUERY_STRING'];
            }
        }
        ## End Code Snippet

        $request = explode('?', $_SERVER['REQUEST_URI']);

        return ((new HttpsDetect())->getUrl()) . '/' . ltrim($request[0], '/');
    }
}