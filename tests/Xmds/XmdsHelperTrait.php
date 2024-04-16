<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
trait XmdsHelperTrait
{
    public function getRf(string $version)
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RequiredFiles>
      <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
      <hardwareKey xsi:type="xsd:string">PHPUnit'.$version.'</hardwareKey>
    </tns:RequiredFiles>
  </soap:Body>
</soap:Envelope>';
    }

    public function notifyStatus(string $version, string $status)
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:NotifyStatus>
      <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
      <hardwareKey xsi:type="xsd:string">PHPUnit'.$version.'</hardwareKey>
      <status xsi:type-="xsd:string">'.$status.'</status>
    </tns:NotifyStatus>
  </soap:Body>
</soap:Envelope>';
    }

    public function register(
        $hardwareKey,
        $displayName,
        $clientType,
        $clientVersion = '4',
        $clientCode = '400',
        $macAddress = 'CC:40:D0:46:3C:A8'
    ) {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:RegisterDisplay>
      <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
      <hardwareKey xsi:type="xsd:string">' . $hardwareKey . '</hardwareKey>
      <displayName xsi:type="xsd:string">' . $displayName . '</displayName>
      <clientType xsi:type="xsd:string">' . $clientType . '</clientType>
      <clientVersion xsi:type="xsd:string">' . $clientVersion . '</clientVersion>
      <clientCode xsi:type="xsd:int">' . $clientCode . '</clientCode>
      <macAddress xsi:type="xsd:string">' . $macAddress . '</macAddress>
    </tns:RegisterDisplay>
  </soap:Body>
</soap:Envelope>';
    }

    public function getSchedule($hardwareKey)
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:Schedule>
      <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
      <hardwareKey xsi:type="xsd:string">' . $hardwareKey . '</hardwareKey>
    </tns:Schedule>
  </soap:Body>
</soap:Envelope>';
    }

    public function reportFault($version)
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
          <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
            <tns:ReportFaults>
              <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
              <hardwareKey xsi:type="xsd:string">PHPUnit'.$version.'</hardwareKey>
              <fault xsi:type="xsd:string">[{"date":"2023-04-20 17:03:52","expires":"2023-04-21 17:03:52","code":"10001","reason":"Test","scheduleId":"0","layoutId":0,"regionId":"0","mediaId":"0","widgetId":"0"}]</fault>
            </tns:ReportFaults>
          </soap:Body>
        </soap:Envelope>';
    }

    public function getWidgetData($version, $widgetId)
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <tns:GetData>
      <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
      <hardwareKey xsi:type="xsd:string">PHPUnit'. $version .'</hardwareKey>
      <widgetId xsi:type="xsd:int">'.$widgetId.'</widgetId>
    </tns:GetData>
  </soap:Body>
</soap:Envelope>';
    }

    public function submitEventLog($version): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
        xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"
          xmlns:tns="urn:xmds" xmlns:types="urn:xmds/encodedTypes"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema">
          <soap:Body soap:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
            <tns:SubmitLog>
              <serverKey xsi:type="xsd:string">6v4RduQhaw5Q</serverKey>
              <hardwareKey xsi:type="xsd:string">PHPUnit'. $version .'</hardwareKey>
              <logXml xsi:type="xsd:string">&lt;log&gt;&lt;event date=&quot;2024-04-10 12:45:55&quot; category=&quot;event&quot;&gt;&lt;eventType&gt;App Start&lt;/eventType&gt;&lt;message&gt;Detailed message about this event&lt;/message&gt;&lt;alertType&gt;both&lt;/alertType&gt;&lt;refId&gt;&lt;/refId&gt;&lt;/event&gt;&lt;/log&gt;</logXml>
            </tns:SubmitLog>
          </soap:Body>
        </soap:Envelope>';
    }
}
