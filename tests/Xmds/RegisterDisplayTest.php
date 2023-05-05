<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Tests\Xmds;

use DOMDocument;
use DOMXPath;
use Xibo\Tests\xmdsTestCase;

/**
 * @property string $registerWindowsXml
 * @property string $registerAndroidXml
 */
class RegisterDisplayTest extends xmdsTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->registerWindowsXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RegisterDisplay>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
      <displayName xsi:type="xsd:string">PHPStorm</displayName>
      <clientType xsi:type="xsd:string">windows</clientType>
      <clientVersion xsi:type="xsd:string">4</clientVersion>
      <clientCode xsi:type="xsd:int">420</clientCode>
      <macAddress xsi:type="xsd:string">CC:40:D0:46:3C:A8</macAddress>
    </tns:RegisterDisplay>
  </soap:Body>
</soap:Envelope>';

        $this->registerAndroidXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RegisterDisplay>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">PHPUnitWaiting</hardwareKey>
      <displayName xsi:type="xsd:string">phpunitwaiting</displayName>
      <clientType xsi:type="xsd:string">android</clientType>
      <clientVersion xsi:type="xsd:string">4</clientVersion>
      <clientCode xsi:type="xsd:int">420</clientCode>
      <macAddress xsi:type="xsd:string">CC:40:D0:46:3C:A8</macAddress>
      <licenceResult xsi:type="xsd:string">trial</licenceResult>
    </tns:RegisterDisplay>
  </soap:Body>
</soap:Envelope>';
    }

    public function testRegisterDisplayAuthed()
    {
        $request = $this->sendRequest('POST', $this->registerWindowsXml, 7);
        $response = $request->getBody()->getContents();

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//ActivationMessage)');
        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($result);

        $this->assertSame('READY', $innerDocument->documentElement->getAttribute('code'));
        $this->assertSame('Display is active and ready to start.', $innerDocument->documentElement->getAttribute('message'));
    }

    public function testRegisterDisplayNoAuth()
    {
        $request = $this->sendRequest('POST', $this->registerAndroidXml, 7);
        $response = $request->getBody()->getContents();

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//ActivationMessage)');
        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($result);

        $this->assertSame('WAITING', $innerDocument->documentElement->getAttribute('code'));
        $this->assertSame(
            'Display is Registered and awaiting Authorisation from an Administrator in the CMS',
            $innerDocument->documentElement->getAttribute('message')
        );

        $array = json_decode(json_encode(simplexml_load_string($result)), true);

        foreach ($array as $key => $value) {
            // $this->getLogger()->debug($key . ' -> ' . json_encode($value));
            if ($key === 'commercialLicence') {
                $this->assertSame('trial', $value);
            }
        }
    }
}
