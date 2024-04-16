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

use DOMDocument;
use DOMXPath;
use Xibo\Tests\XmdsTestCase;

/**
 * Register Displays tests
 */
class RegisterDisplayTest extends XmdsTestCase
{
    use XmdsHelperTrait;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testRegisterDisplayAuthed()
    {
        $request = $this->sendRequest(
            'POST',
            $this->register(
                'PHPUnit7',
                'phpunitv7',
                'android'
            ),
            7
        );

        $response = $request->getBody()->getContents();

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//ActivationMessage)');
        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($result);

        $this->assertSame('READY', $innerDocument->documentElement->getAttribute('code'));
        $this->assertSame(
            'Display is active and ready to start.',
            $innerDocument->documentElement->getAttribute('message')
        );
    }

    public function testRegisterDisplayNoAuth()
    {
        $request = $this->sendRequest(
            'POST',
            $this->register(
                'PHPUnitWaiting',
                'phpunitwaiting',
                'android'
            ),
            7
        );
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
            if ($key === 'commercialLicence') {
                $this->assertSame('trial', $value);
            }
        }
    }

    public function testRegisterNewDisplay()
    {
        $request = $this->sendRequest(
            'POST',
            $this->register(
                'PHPUnitAddedTest' . mt_rand(1, 10),
                'phpunitaddedtest',
                'android'
            ),
            7
        );

        $response = $request->getBody()->getContents();

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXpath($document);
        $result = $xpath->evaluate('string(//ActivationMessage)');
        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($result);

        $this->assertSame('ADDED', $innerDocument->documentElement->getAttribute('code'));
        $this->assertSame(
            'Display is now Registered and awaiting Authorisation from an Administrator in the CMS',
            $innerDocument->documentElement->getAttribute('message')
        );
    }
}
