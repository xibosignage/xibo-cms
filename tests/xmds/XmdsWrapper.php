<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (XmdsWrapper.php)
 */

namespace Xibo\Tests\Xmds;

/**
 * Class XmdsWrapper
 * @package Xibo\Tests\Xmds
 */

class XmdsWrapper
{

    private $URL;
    private $KEY;
    private $version;
    protected $client;

    function __construct($URL = "http://localhost/xmds.php", $KEY="test", $version=5)
    {
        $this->URL = $URL;
        $this->KEY = $KEY;
        $this->version = $version;
        
        ini_set('soap.wsdl_cache_enabled', 0);
        ini_set('soap.wsdl_cache_ttl', 900);
        ini_set('default_socket_timeout', 15);
        
        $options = array(
            'uri'=>'http://schemas.xmlsoap.org/soap/envelope/',
            'style'=>SOAP_RPC,
            'use'=>SOAP_ENCODED,
            'soap_version'=>SOAP_1_1,
            'cache_wsdl'=>WSDL_CACHE_NONE,
            'connection_timeout'=>15,
            'trace'=>true,
            'encoding'=>'UTF-8',
            'exceptions'=>true,
            );
        
        $this->client = new SoapClient($this->URL . '?wsdl', $options);
    }
    
    function RegisterDisplay($hardwareKey, $displayName)
    {
        $params = [ "serverKey" => $this->KEY,
                    "version" => $this->version,
                    "hardwareKey" => $hardwareKey,
                    "displayName" => $displayName ];
        
        $response = $this->client->RegisterDisplay($params);
        return $response;
    }
    
    function RequiredFiles($hardwareKey)
    {
        $params = [ "serverKey" => $this->KEY,
                    "version" => $this->version,
                    "hardwareKey" => $hardwareKey ];
        
        $response = $this->client->RequiredFiles($params);
        return $response;
    }
    
    function GetFile()
    {
    
    }
    
    function Schedule($hardwareKey)
    {
        $params = [ "serverKey" => $this->KEY,
                    "version" => $this->version,
                    "hardwareKey" => $hardwareKey ];
        
        $response = $this->client->Schedule($params);
        return $response;
    }
    
    function BlackList()
    {
    
    }
    
    function SubmitLog()
    {
    
    }
    
    function SubmitStats()
    {
    
    }
    
    function MediaInventory()
    {
    
    }
    
    function GetResource()
    {
    
    }
}

?>