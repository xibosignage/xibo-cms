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
 * @property string $xmlRequest
 */
final class ReportFaultsTest extends xmdsTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->xmlRequest = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
          <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
            <tns:ReportFaults>
              <serverKey xsi:type="xsd:string">test</serverKey>
              <hardwareKey xsi:type="xsd:string">phpstorm</hardwareKey>
              <fault xsi:type="xsd:string">[{date:"2023-04-20 17:03:52",expires:"2023-04-21 17:03:52",code:00001,reason:"Test",scheduleId:0,layoutId:0,regionId:0,mediaId:0,widgetId:0}]</fault>
            </tns:ReportFaults>
          </soap:Body>
        </soap:Envelope>';
    }

    public static function successCases(): array
    {
        return [
            [7],
            [6],
        ];
    }

    public static function failureCases(): array
    {
        return [
            [5],
            [4],
            [3],
        ];
    }

    #[DataProvider('successCases')]
    public function testSendFaultSuccess(int $version)
    {
        $request = $this->sendRequest('POST', $this->xmlRequest, $version);

        $this->assertStringContainsString(
            '<ns1:ReportFaultsResponse><success xsi:type="xsd:boolean">true</success>',
            $request->getBody()->getContents(),
            'Send fault received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testSendFaultFailure(int $version)
    {
        // disable exception on http_error in guzzle, so we can still check the response
        $request = $this->sendRequest('POST', $this->xmlRequest, $version, false);

        // check the fault code
        $this->assertStringContainsString(
            '<faultcode>SOAP-ENV:Server</faultcode>',
            $request->getBody(),
            'Send fault received incorrect response'
        );

        // check the fault string
        $this->assertStringContainsString(
            '<faultstring>Procedure \'ReportFaults\' not present</faultstring>',
            $request->getBody(),
            'Send fault received incorrect response'
        );
    }

    #[DataProvider('failureCases')]
    public function testSendFaultExceptionFailure(int $version)
    {
        // we are expecting 500 Server Exception here for xmds 3,4 and 5
        $this->expectException('GuzzleHttp\Exception\ServerException');
        $this->expectExceptionCode(500);
        $this->sendRequest('POST', $this->xmlRequest, $version);
    }
}
