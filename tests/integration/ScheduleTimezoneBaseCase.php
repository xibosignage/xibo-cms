<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Tests\integration;

use Jenssegers\Date\Date;
use Xibo\Entity\Display;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ScheduleTimezoneTest
 * @package Xibo\Tests\integration
 */
class ScheduleTimezoneBaseCase extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $layout;

    /** @var \Xibo\OAuth2\Client\Entity\XiboDisplay */
    protected $display;

    /** @var \Xibo\OAuth2\Client\Entity\XiboSchedule */
    protected $event;

    protected $timeZone = 'Asia/Hong_Kong';

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for ' . get_class() .' Test');

        // Create a Layout
        $this->layout = $this->createLayout();

        // Checkout
        $layout = $this->checkout($this->layout);

        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);
        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

        // Check us in again
        $this->layout = $this->publish($this->layout);

        // Build the layout
        $this->buildLayout($this->layout);

        // Create a Display
        $this->display = $this->createDisplay();

        $this->displaySetTimezone($this->display, $this->timeZone);
        $this->displaySetStatus($this->display, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display);

        // Make sure the Layout Status is as we expect
        $this->assertTrue($this->layoutStatusEquals($this->layout, 1), 'Layout Status isnt as expected');

        // Make sure our Display is already DONE
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Check our timzone is set correctly
        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->RegisterDisplay($this->display->license, $this->timeZone));
        $this->assertEquals($this->timeZone, $xml->documentElement->getAttribute('localTimezone'), 'Timezone not correct');
        $xml = null;

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the Display
        $this->deleteDisplay($this->display);
    }
    // </editor-fold>

    public function testSchedule()
    {
        // Our CMS is in GMT
        // Create a schedule one hours time in my player timezone
        $localNow = Date::now()->setTimezone($this->timeZone);
        $date = $localNow->copy()->addHour()->startOfHour();

        $this->getLogger()->debug('Event start will be at: ' . $date->format('Y-m-d H:i:s'));

        $response = $this->client->post('/schedule', [
            'fromDt' => $date->format('Y-m-d H:i:s'),
            'toDt' => $date->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'eventTypeId' => 1,
            'campaignId' => $this->layout->campaignId,
            'displayGroupIds' => [$this->display->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 0,
            'scheduleRecurrenceType' => null,
            'scheduleRecurrenceDetail' => null,
            'scheduleRecurrenceRange' => null,
            'syncTimezone' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->Schedule($this->display->license));
        $this->getLogger()->debug($xml->saveXML());

        // Check the filter from and to dates are correct
        $this->assertEquals($localNow->startOfHour()->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterFrom'), 'Filter from date incorrect');
        $this->assertEquals($localNow->addDays(2)->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterTo'), 'Filter to date incorrect');

        // Check our event is present.
        $layouts = $xml->getElementsByTagName('layout');

        $this->assertTrue(count($layouts) == 1, 'Unexpected number of events');

        foreach ($layouts as $layout) {
            $xmlFromDt = $layout->getAttribute('fromdt');
            $this->assertEquals($date->format('Y-m-d H:i:s'), $xmlFromDt, 'From date doesnt match: ' . $xmlFromDt);
        }
    }

    public function testRecurringSchedule()
    {
        // Our CMS is in GMT
        // Create a schedule one hours time in my player timezone
        // we start this schedule the day before
        $localNow = Date::now()->setTimezone($this->timeZone);
        $date = $localNow->copy()->subDay()->addHour()->startOfHour();

        $this->getLogger()->debug('Event start will be at: ' . $date->format('Y-m-d H:i:s'));

        $response = $this->client->post('/schedule', [
            'fromDt' => $date->format('Y-m-d H:i:s'),
            'toDt' => $date->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'eventTypeId' => 1,
            'campaignId' => $this->layout->campaignId,
            'displayGroupIds' => [$this->display->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 0,
            'recurrenceType' => 'Day',
            'recurrenceDetail' => 1,
            'recurrenceRange' => null,
            'syncTimezone' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->Schedule($this->display->license));
        //$this->getLogger()->debug($xml->saveXML());

        // Check the filter from and to dates are correct
        $this->assertEquals($localNow->startOfHour()->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterFrom'), 'Filter from date incorrect');
        $this->assertEquals($localNow->addDays(2)->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterTo'), 'Filter to date incorrect');

        // Check our event is present.
        $layouts = $xml->getElementsByTagName('layout');

        foreach ($layouts as $layout) {
            // Move our day on (we know we're recurring by day), and that we started a day behind
            $date->addDay();

            $xmlFromDt = $layout->getAttribute('fromdt');
            $this->assertEquals($date->format('Y-m-d H:i:s'), $xmlFromDt, 'From date doesnt match: ' . $xmlFromDt);
        }
    }

    public function testSyncedSchedule()
    {
        // Our CMS is in GMT
        // Create a schedule one hours time in my CMS timezone
        $localNow = Date::now()->setTimezone($this->timeZone);

        // If this was 8AM local CMS time, we would expect the resulting date/times in the XML to have the equivilent
        // timezone specific date/times
        $date = Date::now()->copy()->addHour()->startOfHour();
        $localDate = $date->copy()->timezone($this->timeZone);

        $this->getLogger()->debug('Event start will be at: ' . $date->format('Y-m-d H:i:s') . ' which is ' . $localDate->format('Y-m-d H:i:s') . ' local time.');

        $response = $this->client->post('/schedule', [
            'fromDt' => $date->format('Y-m-d H:i:s'),
            'toDt' => $date->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'eventTypeId' => 1,
            'campaignId' => $this->layout->campaignId,
            'displayGroupIds' => [$this->display->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 0,
            'recurrenceType' => null,
            'recurrenceDetail' => null,
            'recurrenceRange' => null,
            'syncTimezone' => 1
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->Schedule($this->display->license));
        //$this->getLogger()->debug($xml->saveXML());

        // Check the filter from and to dates are correct
        $this->assertEquals($localNow->startOfHour()->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterFrom'), 'Filter from date incorrect');
        $this->assertEquals($localNow->addDays(2)->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterTo'), 'Filter to date incorrect');

        // Check our event is present.
        $layouts = $xml->getElementsByTagName('layout');

        foreach ($layouts as $layout) {
            $xmlFromDt = $layout->getAttribute('fromdt');
            $this->assertEquals($localDate->format('Y-m-d H:i:s'), $xmlFromDt, 'From date doesnt match: ' . $xmlFromDt);
        }
    }

    public function testSyncedRecurringSchedule()
    {
        // Our CMS is in GMT
        // Create a schedule one hours time in my CMS timezone
        $localNow = Date::now()->setTimezone($this->timeZone);

        // If this was 8AM local CMS time, we would expect the resulting date/times in the XML to have the equivilent
        // timezone specific date/times
        $date = Date::now()->copy()->subDay()->addHour()->startOfHour();
        $localDate = $date->copy()->timezone($this->timeZone);

        $this->getLogger()->debug('Event start will be at: ' . $date->format('Y-m-d H:i:s') . ' which is ' . $localDate->format('Y-m-d H:i:s') . ' local time.');

        $response = $this->client->post('/schedule', [
            'fromDt' => $date->format('Y-m-d H:i:s'),
            'toDt' => $date->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'eventTypeId' => 1,
            'campaignId' => $this->layout->campaignId,
            'displayGroupIds' => [$this->display->displayGroupId],
            'displayOrder' => 1,
            'isPriority' => 0,
            'recurrenceType' => 'Day',
            'recurrenceDetail' => 1,
            'recurrenceRange' => null,
            'syncTimezone' => 1
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);

        $xml = new \DOMDocument();
        $xml->loadXML($this->getXmdsWrapper()->Schedule($this->display->license));
        $this->getLogger()->debug($xml->saveXML());

        // Check the filter from and to dates are correct
        $this->assertEquals($localNow->startOfHour()->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterFrom'), 'Filter from date incorrect');
        $this->assertEquals($localNow->addDays(2)->format('Y-m-d H:i:s'), $xml->documentElement->getAttribute('filterTo'), 'Filter to date incorrect');

        // Check our event is present.
        $layouts = $xml->getElementsByTagName('layout');

        foreach ($layouts as $layout) {
            // Move our day on (we know we're recurring by day), and that we started a day behind
            $localDate->addDay();

            $xmlFromDt = $layout->getAttribute('fromdt');
            $this->assertEquals($localDate->format('Y-m-d H:i:s'), $xmlFromDt, 'From date doesnt match: ' . $xmlFromDt);
        }
    }
}