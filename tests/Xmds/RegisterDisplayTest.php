<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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

    // The display name column holds 50 characters. A longer name is shortened rather than failing
    // every registration.
    public function testRegisterNewDisplayWithANameTooLongForTheColumn()
    {
        // A fixed hardware key, so running the suite again doesn't add another display each time.
        $hardwareKey = 'PHPUnitLongName';

        $request = $this->sendRequest(
            'POST',
            $this->register(
                $hardwareKey,
                str_repeat('n', 60),
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

        // Added on a fresh database, as in CI. Running again on the same database finds the
        // display the first run added, which still has to hold the shortened name.
        $this->assertContains(
            $innerDocument->documentElement->getAttribute('code'),
            ['ADDED', 'WAITING', 'READY']
        );
        $this->assertSame(str_repeat('n', 50), $this->getSavedDisplayName($hardwareKey));
    }

    /**
     * The name the CMS saved for a hardware key. The response only carries it for an authorised
     * display, so it is read from the CMS database, which the suite reaches from inside the CMS
     * container.
     */
    private function getSavedDisplayName(string $hardwareKey): ?string
    {
        // Sets $dbhost, $dbuser, $dbpass and $dbname.
        require PROJECT_ROOT . '/web/settings.php';

        [$host, $port] = array_pad(explode(':', $dbhost, 2), 2, '3306');

        $pdo = new \PDO(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=utf8mb4',
            $dbuser,
            $dbpass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $statement = $pdo->prepare('SELECT `display` FROM `display` WHERE `license` = :hardwareKey');
        $statement->execute(['hardwareKey' => $hardwareKey]);
        $name = $statement->fetchColumn();

        return $name === false ? null : $name;
    }
}
