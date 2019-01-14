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

    function __construct($URL = "http://localhost/xmds.php", $KEY="test", $version='5')
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
        
        $this->client = new \SoapClient($this->URL . '?wsdl&v=' . $this->version, $options);
    }
    
    function RegisterDisplay($hardwareKey, $displayName, $clientType='windows', $clientVersion='', $clientCode='', $operatingSystem='', $macAddress='', $xmrChannel='', $xmrPubKey='')
    {
        $response = $this->client->RegisterDisplay($this->KEY,
                                                   $hardwareKey,
                                                   $displayName,
                                                   $clientType,
                                                   $clientVersion,
                                                   $clientCode,
                                                   $operatingSystem,
                                                   $macAddress,
                                                   $xmrChannel,
                                                   $xmrPubKey);
        return $response;
    }
    
    function RequiredFiles($hardwareKey)
    {
        $response = $this->client->RequiredFiles($this->KEY,
                                                 $hardwareKey);
        return $response;
    }
    
    function GetFile()
    {
    
    }
    
    function Schedule($hardwareKey)
    {
        $response = $this->client->Schedule($this->KEY,
                                            $hardwareKey);
        return $response;
    }
    
    function BlackList()
    {
    
    }
    
    function SubmitLog()
    {
    
    }

    function SubmitStats($hardwareKey, $statXml)
    {
        $response = $this->client->SubmitStats($this->KEY,
                                               $hardwareKey,
                                               $statXml);
        return $response;
    
    }
    
    function MediaInventory()
    {
    
    }

    /**
     * @param string $hardwareKey
     * @param int $layoutId
     * @param int $regionId
     * @param string $mediaId
     * @return string
     */
    function GetResource($hardwareKey, $layoutId, $regionId, $mediaId)
    {
        return $this->client->GetResource($this->KEY, $hardwareKey, $layoutId, $regionId, $mediaId);
    }
    
    function NotifyStatus()
    {
    
    }
    
    function SubmitScreenShot()
    {
    
    }
}

?>