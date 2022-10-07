<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

    /**
     * XmdsWrapper constructor.
     * @param string $URL
     * @param string $KEY
     * @param string $version
     * @throws \SoapFault
     */
    public function __construct($URL = 'http://localhost/xmds.php', $KEY = 'test', $version = '7')
    {
        $this->URL = $URL;
        $this->KEY = $KEY;
        $this->version = $version;
        
        ini_set('soap.wsdl_cache_enabled', 0);
        ini_set('soap.wsdl_cache_ttl', 900);
        ini_set('default_socket_timeout', 15);
        
        $options = [
            'uri'=>'http://schemas.xmlsoap.org/soap/envelope/',
            'style'=>SOAP_RPC,
            'use'=>SOAP_ENCODED,
            'soap_version'=>SOAP_1_1,
            'cache_wsdl'=>WSDL_CACHE_NONE,
            'connection_timeout'=>15,
            'trace'=>true,
            'encoding'=>'UTF-8',
            'exceptions'=>true,
        ];
        
        $this->client = new \SoapClient($this->URL . '?wsdl&v=' . $this->version, $options);
    }

    /**
     * @param $hardwareKey
     * @param $displayName
     * @param string $clientType
     * @param string $clientVersion
     * @param string $clientCode
     * @param string $operatingSystem
     * @param string $macAddress
     * @param string $xmrChannel
     * @param string $xmrPubKey
     * @return mixed
     * @throws \SoapFault
     */
    function RegisterDisplay($hardwareKey, $displayName, $clientType='windows', $clientVersion='', $clientCode='', $operatingSystem='', $macAddress='', $xmrChannel='', $xmrPubKey='')
    {
        return $this->client->RegisterDisplay($this->KEY,
            $hardwareKey,
            $displayName,
            $clientType,
            $clientVersion,
            $clientCode,
            $operatingSystem,
            $macAddress,
            $xmrChannel,
            $xmrPubKey
        );
    }

    /**
     * Request Required Files
     * @param $hardwareKey
     * @return mixed
     * @throws \SoapFault
     */
    function RequiredFiles($hardwareKey)
    {
        return $this->client->RequiredFiles($this->KEY, $hardwareKey);
    }

    /**
     * Request a file
     * @param $hardwareKey
     * @param $fileId
     * @param $fileType
     * @param $chunkOffset
     * @param $chunkSize
     * @return mixed
     * @throws \SoapFault
     */
    function GetFile($hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize)
    {
        return $this->client->GetFile($this->KEY,
            $hardwareKey,
            $fileId,
            $fileType,
            $chunkOffset,
            $chunkSize
        );
    }

    /**
     * Request Schedule
     * @param $hardwareKey
     * @return mixed
     * @throws \SoapFault
     */
    function Schedule($hardwareKey)
    {
        return $this->client->Schedule($this->KEY, $hardwareKey);
    }
    
    function BlackList()
    {
    
    }
    
    function SubmitLog()
    {
    
    }

    /**
     * Submit Stats
     * @param $hardwareKey
     * @param $statXml
     * @return mixed
     * @throws \SoapFault
     */
    function SubmitStats($hardwareKey, $statXml)
    {
        return $this->client->SubmitStats($this->KEY, $hardwareKey, $statXml);
    
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
     * @throws \SoapFault
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
