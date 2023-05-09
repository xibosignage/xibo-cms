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
use Xibo\Tests\XmdsTestCase;

/**
 * @property string $requiredFilesXml
 * @property string $requiredFilesXmlv6
 */
class GetDependencyTest extends XmdsTestCase
{
    public function setUp(): void
    {
        parent::setUp();

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
        
        $this->requiredFilesXmlv6 = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RequiredFiles>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpunit6</hardwareKey>
    </tns:RequiredFiles>
  </soap:Body>
</soap:Envelope>';
    }

    public static function successCasesBundle(): array
    {
        return [
            [7],
        ];
    }

    public static function successCasesFont(): array
    {
        return [
            [7, 'Aileron-Heavy.otf'],
            [7, 'fonts.css'],
            [6, 'Aileron-Heavy.otf'],
            [6, 'fonts.css'],
            [5, 'Aileron-Heavy.otf'],
            [5, 'fonts.css'],
            [4, 'Aileron-Heavy.otf'],
            [4, 'fonts.css'],
        ];
    }

    public static function successCasesBundleOld(): array
    {
        return [
            [6],
            [5],
            [4],
        ];
    }

    #[DataProvider('successCasesFont')]
    public function testGetFont($version, $fileName)
    {
        if ($version === 7) {
            $rf = $this->sendRequest('POST', $this->requiredFilesXml, $version);
        } else {
            $rf = $this->sendRequest('POST', $this->requiredFilesXmlv6, $version);
        }

        $response = $rf->getBody()->getContents();
        $path = null;

        $document = new DOMDocument();
        $document->loadXML($response);
        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//RequiredFilesXml)');
        $array = json_decode(json_encode(simplexml_load_string($result)), true);

        foreach ($array as $item) {
            foreach ($item as $file) {
                if (!empty($file['@attributes'])) {
                    if ($file['@attributes']['saveAs'] === $fileName) {
                        if ($version === 7) {
                            $this->assertSame('dependency', $file['@attributes']['type']);
                        } else {
                            $this->assertSame('media', $file['@attributes']['type']);
                        }

                        $path = strstr($file['@attributes']['path'], '?');
                    }
                }
            }
        }

        $this->assertNotEmpty($path);

        // Font dependency is still http download, try to get it here
        $getFile = $this->getFile($path);
        $this->assertSame(200, $getFile->getStatusCode());
        $this->assertNotEmpty($getFile->getBody()->getContents());
    }

    #[DataProvider('successCasesBundle')]
    public function testGetBundlev7($version)
    {
        $rf = $this->sendRequest('POST', $this->requiredFilesXml, $version);
        $response = $rf->getBody()->getContents();
        $size = null;
        $id = null;
        $type = null;

        $document = new DOMDocument();
        $document->loadXML($response);
        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//RequiredFilesXml)');
        $array = json_decode(json_encode(simplexml_load_string($result)), true);

        foreach ($array as $item) {
            foreach ($item as $file) {
                if (!empty($file['@attributes'])) {
                    if ($file['@attributes']['saveAs'] === 'bundle.min.js') {
                        $size = $file['@attributes']['size'];
                        $type = $file['@attributes']['fileType'];
                        $id = $file['@attributes']['id'];
                    }
                }

            }
        }

        $this->assertNotEmpty($size);
        $this->assertNotEmpty($type);
        $this->assertNotEmpty($id);

        // construct the xml for GetDependency wsdl request
        $bundleXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:GetDependency>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
      <fileType xsi:type="xsd:string">'. $type .'</fileType>
      <id xsi:type="xsd:string">'. $id .'</id>
      <chunkOffset xsi:type="xsd:double">0</chunkOffset>
      <chunkSize xsi:type="xsd:double">'. $size .'</chunkSize>
    </tns:GetDependency>
  </soap:Body>
</soap:Envelope>';

        // try to call GetDependency with our xml
        $getBundle = $this->sendRequest('POST', $bundleXml, $version);
        $getBundleResponse = $getBundle->getBody()->getContents();
        // expect success
        $this->assertSame(200, $getBundle->getStatusCode());
        // expect not empty body
        $this->assertNotEmpty($getBundleResponse);
        // expect response format
        $this->assertStringContainsString(
            '<ns1:GetDependencyResponse><file xsi:type="xsd:base64Binary">',
            $getBundleResponse,
            'GetDependency getBundle received incorrect response'
        );
    }

    #[DataProvider('successCasesBundleOld')]
    public function testGetBundlev6($version)
    {
        $rf = $this->sendRequest('POST', $this->requiredFilesXmlv6, $version);
        $response = $rf->getBody()->getContents();
        $size = null;
        $id = null;
        $type = null;

        $document = new DOMDocument();
        $document->loadXML($response);
        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//RequiredFilesXml)');
        $array = json_decode(json_encode(simplexml_load_string($result)), true);

        foreach ($array as $item) {
            foreach ($item as $file) {
                if (!empty($file['@attributes'])) {
                    if ($file['@attributes']['saveAs'] === 'bundle.min.js') {
                        $size = $file['@attributes']['size'];
                        $type = $file['@attributes']['type'];
                        $id = $file['@attributes']['id'];
                    }
                }
            }
        }

        $this->assertNotEmpty($size);
        $this->assertNotEmpty($type);
        $this->assertNotEmpty($id);

        // construct the xml for GetDependency wsdl request
        $bundleXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:GetFile>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpunit6</hardwareKey>
      <fileId xsi:type="xsd:string">'. $id .'</fileId>
      <fileType xsi:type="xsd:string">'. $type .'</fileType>
      <chunkOffset xsi:type="xsd:double">0</chunkOffset>
      <chuckSize xsi:type="xsd:double">'. $size .'</chuckSize>
    </tns:GetFile>
  </soap:Body>
</soap:Envelope>';

        // try to call GetFile with our xml
        $getBundle = $this->sendRequest('POST', $bundleXml, $version);
        $getBundleResponse = $getBundle->getBody()->getContents();
        // expect success
        $this->assertSame(200, $getBundle->getStatusCode());
        // expect not empty body
        $this->assertNotEmpty($getBundleResponse);
        // expect response format
        $this->assertStringContainsString(
            '<ns1:GetFileResponse><file xsi:type="xsd:base64Binary">',
            $getBundleResponse,
            'GetDependency getBundle received incorrect response'
        );
    }
}
