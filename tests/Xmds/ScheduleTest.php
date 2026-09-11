<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\DataProvider;
use Xibo\Tests\xmdsTestCase;

/**
 * Schedule tests.
 */
class ScheduleTest extends XmdsTestCase
{
    use XmdsHelperTrait;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * One seeded display per protocol version.
     *
     * The Schedule response is cached per display (`$display->getCacheKey() . '/schedule'`), so
     * pairing each version with its own display means each case really does generate a fresh
     * response rather than being served the previous case's cache entry.
     *
     * @return array
     */
    public static function commandScheduleCases(): array
    {
        return [
            'v7' => ['PHPUnit7', '7'],
            'v6' => ['PHPUnit6', '6'],
            'v5' => ['PHPUnit5', '5'],
            'v4' => ['PHPUnit4', '4'],
        ];
    }

    /**
     * A repeating Command event must not break the Schedule response.
     *
     * Command events are the one event type that legitimately stores a NULL `toDt` - a command
     * fires at an instant and has no end. In 4.5.1, doSchedule() passed that NULL straight into
     * Carbon::createFromTimestamp(), which throws under Carbon 3. The throw escaped doSchedule()
     * and took the *whole* Schedule response with it, so every player on that display stopped
     * receiving its schedule - not just the command. See xibosignage/xibo#3931.
     *
     * The fixture is seeded by SeedDatabaseTask::createCommandSchedules().
     *
     * NOTE the Schedule response is cached per display (`$display->getCacheKey() . '/schedule'`),
     * and in a container CMS that pool is memcached, not the `cache/` directory. CI is fine because
     * its containers start cold, but running this against a warm stack after changing server-side
     * code will pass on a cached response and tell you nothing. Flush the cache first:
     *
     *   docker-compose exec memcached sh -c 'echo flush_all | nc localhost 11211'
     *
     * @throws GuzzleException
     */
    #[DataProvider('commandScheduleCases')]
    public function testScheduleWithRepeatingCommandEvent(string $hardwareKey, string $version): void
    {
        // httpErrors is off on purpose: a broken schedule comes back as an HTTP 500 carrying a SOAP
        // fault, and we want to assert on that body rather than have Guzzle throw over the top of it.
        $request = $this->sendRequest('POST', $this->getSchedule($hardwareKey), $version, false);
        $response = $request->getBody()->getContents();

        // The whole response used to be replaced by a SOAP fault, so check that first and give a
        // useful failure message - this single assertion is the regression guard.
        $this->assertStringNotContainsString(
            'Fault',
            $response,
            'Schedule returned a SOAP fault rather than a schedule for ' . $hardwareKey
        );

        $document = new DOMDocument();
        $document->loadXML($response);

        $xpath = new DOMXPath($document);
        $scheduleXml = $xpath->evaluate('string(//ScheduleXml)');

        $this->assertNotEmpty($scheduleXml, 'Schedule returned an empty ScheduleXml for ' . $hardwareKey);

        $innerDocument = new DOMDocument();
        $innerDocument->loadXML($scheduleXml);

        // The repeating command event reaches the player.
        $commands = $innerDocument->documentElement->getElementsByTagName('command');
        $this->assertGreaterThan(
            0,
            $commands->length,
            'The repeating Command event was not scheduled to ' . $hardwareKey
        );

        $codes = [];
        foreach ($commands as $command) {
            $codes[] = $command->getAttribute('code');

            // A NULL toDt must not leave the occurrence without a date.
            $this->assertNotEmpty(
                $command->getAttribute('date'),
                'A scheduled command has no date attribute'
            );
        }

        $this->assertContains('TIMEZONE', $codes, 'The seeded command was not in the schedule');

        // The rest of the schedule survives. This is the part that actually hurt in 4.5.1: one
        // command event with a NULL toDt meant the player got no layouts either.
        $this->assertGreaterThan(
            0,
            $innerDocument->documentElement->getElementsByTagName('layout')->length,
            'The layout events are missing from the schedule for ' . $hardwareKey
        );
    }
}
