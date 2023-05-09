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
use Xibo\Tests\XmdsTestCase;

/**
 * @property string $dataSetXml
 * @property string $requiredFilesXml
 * @property string $requiredFilesXmlv6
 */
class GetDataTest extends XmdsTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $registerXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
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

        // to make sure Display is logged in, otherwise WidgetSyncTask will not sync data.
        $this->sendRequest('POST', $registerXml, 7);

        $this->dataSetXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:GetData>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
      <widgetId xsi:type="xsd:int">112</widgetId>
    </tns:GetData>
  </soap:Body>
</soap:Envelope>';


        $this->requiredFilesXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RequiredFiles>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
    </tns:RequiredFiles>
  </soap:Body>
</soap:Envelope>';
    }
    public function testGetData()
    {
        // Fresh RF
        $this->sendRequest('POST', $this->requiredFilesXml, 7);

        // Execute Widget Sync task so we can have data for our Widget
        exec('cd /var/www/cms; php bin/run.php 9');

        // XMDS GetData with our dataSet Widget
        $response = $this->sendRequest('POST', $this->dataSetXml);
        $content = $response->getBody()->getContents();

        // expect GetDataResponse
        $this->assertStringContainsString(
            '<ns1:GetDataResponse><data xsi:type="xsd:string">',
            $content,
            'GetData received incorrect response'
        );

        $document = new DOMDocument();
        $document->loadXML($content);
        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//data)');

        $array = json_decode($result, true);

        // go through GetData response and see what we have
        foreach ($array as $key => $item) {
            // data and meta expected to not be empty
            if ($key === 'data' || $key === 'meta') {
                $this->assertNotEmpty($item);
                $this->assertNotEmpty($key);
            }

            if ($key === 'data') {
                $i = 0;
                // go through the expected 2 rows in our dataSet data and see if the column/value matches
                foreach ($item as $row) {
                    $this->assertNotEmpty($row);
                    if ($i === 0) {
                        $this->assertSame('Example text value', $row['Text']);
                        $this->assertSame(1, $row['Number']);
                    } else if ($i === 1) {
                        $this->assertSame('PHPUnit text', $row['Text']);
                        $this->assertSame(2, $row['Number']);
                    }
                    $i++;
                }
            }
        }
    }
}
