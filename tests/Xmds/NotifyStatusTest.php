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

use PHPUnit\Framework\Attributes\DataProvider;
use Xibo\Tests\xmdsTestCase;

/**
 * @property string $currentLayoutXml
 * @property string $geoLocationXml
 * @property string $orientationXml
 */
final class NotifyStatusTest extends xmdsTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->currentLayoutXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:NotifyStatus>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
      <status xsi:type-="xsd:string">{"currentLayoutId":1}</status>
    </tns:NotifyStatus>
  </soap:Body>
</soap:Envelope>';

        $this->geoLocationXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:NotifyStatus>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
      <status xsi:type-="xsd:string">{"latitude":52.3676, "longitude":4.9041}</status>
    </tns:NotifyStatus>
  </soap:Body>
</soap:Envelope>';

        $this->orientationXml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:NotifyStatus>
      <serverKey xsi:type="xsd:string">test</serverKey>
      <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
      <status xsi:type-="xsd:string">{"width":7680, "height":4320}</status>
    </tns:NotifyStatus>
  </soap:Body>
</soap:Envelope>';
    }

    public static function successCases(): array
    {
        return [
            [7],
            [6],
            [5],
            [4],
        ];
    }

    public static function failureCases(): array
    {
        return [
            [3],
        ];
    }

    #[DataProvider('successCases')]
    public function testCurrentLayout(int $version)
    {
        $request = $this->sendRequest('POST', $this->currentLayoutXml, $version);

        $this->assertStringContainsString(
            '<ns1:NotifyStatusResponse><success xsi:type="xsd:boolean">true</success></ns1:NotifyStatusResponse>',
            $request->getBody()->getContents(),
            'Notify Current Layout received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testCurrentLayoutFailure(int $version)
    {
        // disable exception on http_error in guzzle, so we can still check the response
        $request = $this->sendRequest('POST', $this->currentLayoutXml, $version, false);

        $this->assertSame(500, $request->getStatusCode());
        // check the fault code
        $this->assertStringContainsString(
            '<faultcode>SOAP-ENV:Server</faultcode>',
            $request->getBody(),
            'Notify Current Layout received incorrect response'
        );

        // check the fault string
        $this->assertStringContainsString(
            '<faultstring>Procedure \'NotifyStatus\' not present</faultstring>',
            $request->getBody(),
            'Notify Current Layout received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testCurrentLayoutExceptionFailure(int $version)
    {
        // we are expecting 500 Server Exception here for xmds 3
        $this->expectException('GuzzleHttp\Exception\ServerException');
        $this->expectExceptionCode(500);
        $this->sendRequest('POST', $this->currentLayoutXml, $version);
    }

    #[DataProvider('successCases')]
    public function testGeoLocation($version)
    {
        $request = $this->sendRequest('POST', $this->geoLocationXml, $version);

        $this->assertStringContainsString(
            '<ns1:NotifyStatusResponse><success xsi:type="xsd:boolean">true</success></ns1:NotifyStatusResponse>',
            $request->getBody()->getContents(),
            'Notify Geo Location received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testGeoLocationFailure(int $version)
    {
        // disable exception on http_error in guzzle, so we can still check the response
        $request = $this->sendRequest('POST', $this->geoLocationXml, $version, false);

        $this->assertSame(500, $request->getStatusCode());
        // check the fault code
        $this->assertStringContainsString(
            '<faultcode>SOAP-ENV:Server</faultcode>',
            $request->getBody(),
            'Notify Geo Location received incorrect response'
        );

        // check the fault string
        $this->assertStringContainsString(
            '<faultstring>Procedure \'NotifyStatus\' not present</faultstring>',
            $request->getBody(),
            'Notify Geo Location received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testGeoLocationExceptionFailure(int $version)
    {
        // we are expecting 500 Server Exception here for xmds 3
        $this->expectException('GuzzleHttp\Exception\ServerException');
        $this->expectExceptionCode(500);
        $this->sendRequest('POST', $this->geoLocationXml, $version);
    }

    #[DataProvider('successCases')]
    public function testOrientation(int $version)
    {
        $request = $this->sendRequest('POST', $this->orientationXml, $version);

        $this->assertStringContainsString(
            '<ns1:NotifyStatusResponse><success xsi:type="xsd:boolean">true</success></ns1:NotifyStatusResponse>',
            $request->getBody()->getContents(),
            'Notify Orientation received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testOrientationFailure(int $version)
    {
        // disable exception on http_error in guzzle, so we can still check the response
        $request = $this->sendRequest('POST', $this->orientationXml, $version, false);

        $this->assertSame(500, $request->getStatusCode());
        // check the fault code
        $this->assertStringContainsString(
            '<faultcode>SOAP-ENV:Server</faultcode>',
            $request->getBody(),
            'Notify Orientation received incorrect response'
        );

        // check the fault string
        $this->assertStringContainsString(
            '<faultstring>Procedure \'NotifyStatus\' not present</faultstring>',
            $request->getBody(),
            'Notify Orientation received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testOrientationExceptionFailure(int $version)
    {
        // we are expecting 500 Server Exception here for xmds 3
        $this->expectException('GuzzleHttp\Exception\ServerException');
        $this->expectExceptionCode(500);
        $this->sendRequest('POST', $this->orientationXml, $version);
    }
}
