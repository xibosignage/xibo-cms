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
 * Get data tests for xmds v7
 * @property string $dataSetXml
 */
class GetDataTest extends XmdsTestCase
{
    // The widgetId of our expected widget (if we change the default layout this ID will change).
    const WIDGET_ID = 7;

    use XmdsHelperTrait;
    public function setUp(): void
    {
        parent::setUp();

        // to make sure Display is logged in, otherwise WidgetSyncTask will not sync data.
        $this->sendRequest(
            'POST',
            $this->register(
                'PHPUnit7',
                'phpunitv7',
                'android'
            ),
            7
        );
    }
    public function testGetData()
    {
        // Fresh RF
        $this->sendRequest('POST', $this->getRf(7), 7);

        // Execute Widget Sync task so we can have data for our Widget
        exec('cd /var/www/cms; php bin/run.php 9');

        // XMDS GetData with our dataSet Widget
        $response = $this->sendRequest('POST', $this->getWidgetData(7, self::WIDGET_ID));
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
