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
use PHPUnit\Framework\Attributes\DataProvider;
use Xibo\Tests\xmdsTestCase;

/**
 * @property string $scheduleXml
 * @property string $registerXml
 * @property string $registerLeadXml
 */
class SyncTest extends XmdsTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->scheduleXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:Schedule>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpunitsync</hardwareKey>
    </tns:Schedule>
  </soap:Body>
</soap:Envelope>';

        $this->registerXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RegisterDisplay>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpunitsync</hardwareKey>
      <displayName xsi:type="xsd:string">PHPUnitSync</displayName>
      <clientType xsi:type="xsd:string">android</clientType>
      <clientVersion xsi:type="xsd:string">4</clientVersion>
      <clientCode xsi:type="xsd:int">420</clientCode>
      <macAddress xsi:type="xsd:string">CC:40:D0:46:3C:A8</macAddress>
      <licenceResult xsi:type="xsd:string">licensed</licenceResult>
    </tns:RegisterDisplay>
  </soap:Body>
</soap:Envelope>';

        $this->registerLeadXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RegisterDisplay>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpunit6</hardwareKey>
      <displayName xsi:type="xsd:string">PHPUnit_v6</displayName>
      <clientType xsi:type="xsd:string">android</clientType>
      <clientVersion xsi:type="xsd:string">4</clientVersion>
      <clientCode xsi:type="xsd:int">420</clientCode>
      <macAddress xsi:type="xsd:string">CC:40:D0:46:3C:A8</macAddress>
      <licenceResult xsi:type="xsd:string">licensed</licenceResult>
    </tns:RegisterDisplay>
  </soap:Body>
</soap:Envelope>';
    }

    public static function registerSuccessCases(): array
    {
        return [
            [7],
            [6],
        ];
    }

    public function testScheduleSyncEvent()
    {
        $request = $this->sendRequest('POST', $this->scheduleXml, 7);

        $response = $request->getBody()->getContents();

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//ScheduleXml)');
        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($result);
        $layouts = $innerDocument->documentElement->getElementsByTagName('layout');

        foreach ($layouts as $layout) {
            $this->assertSame('165', $layout->getAttribute('file'));
            $this->assertSame('1', $layout->getAttribute('syncEvent'));
            $this->assertSame('64', $layout->getAttribute('scheduleid'));
        }
    }

    #[DataProvider('registerSuccessCases')]
    public function testRegisterDisplay($version)
    {
        if ($version === 7) {
            $xml = $this->registerXml;
        } else {
            $xml = $this->registerLeadXml;
        }

        $request = $this->sendRequest('POST', $xml, $version);
        $response = $request->getBody()->getContents();

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//ActivationMessage)');
        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($result);

        $this->assertSame('READY', $innerDocument->documentElement->getAttribute('code'));
        $this->assertSame('Display is active and ready to start.', $innerDocument->documentElement->getAttribute('message'));

        if ($version === 7) {
            $this->assertSame('192.168.0.3', $innerDocument->documentElement->getAttribute('syncGroup'));
        } else {
            $this->assertSame('lead', $innerDocument->documentElement->getAttribute('syncGroup'));
        }
    }
}
