<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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

namespace Xibo\Tests\Integration;

use Carbon\Carbon;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboStats;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsTest
 * @package Xibo\Tests\Integration
 */
class StatisticsTest extends LocalWebTestCase
{
    use LayoutHelperTrait, DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboLibrary */
    protected $media;

    /** @var XiboLibrary */
    protected $media2;

    /** @var \Xibo\OAuth2\Client\Entity\XiboWidget */
    private $widget;

    /** @var \Xibo\OAuth2\Client\Entity\XiboWidget */
    private $widget2;

    /** @var \Xibo\OAuth2\Client\Entity\XiboWidget */
    private $textWidget;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        // Create a Layout
        $this->layout = $this->createLayout();

        // Create a Display
        $this->display = $this->createDisplay();
        $this->displaySetLicensed($this->display);

        // Upload some media
        $this->media = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/xts-night-001.jpg');

        $this->media2 = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/xts-layout-003-background.jpg');

        // Checkout our Layout and add some Widgets to it.
        $layout = $this->getDraft($this->layout);

        // Create and assign new text widget
        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);

        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

        $this->textWidget = (new XiboText($this->getEntityProvider()))->hydrate($response);

        // Add another region
        // Assign media to the layouts default region.
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$this->media->mediaId, $this->media2->mediaId], 10, $layout->regions[0]->regionPlaylist->playlistId);

        // Get Widget Ids
        $this->widget = $playlist->widgets[0];
        $this->widget2 = $playlist->widgets[1];

        // Publish the Layout
        $this->layout = $this->publish($this->layout);

        // Record some stats for all tests to use.
        // One word name for the event
        $eventName = Random::generateString(10, 'event');

        $response = $this->getXmdsWrapper()->SubmitStats($this->display->license, '<stats>
            <stat fromdt="2018-02-12 00:00:00" todt="2018-02-15 00:00:00" type="layout" scheduleid="0" 
                layoutid="' . $this->layout->layoutId . '" />
            <stat fromdt="2018-02-12 00:00:00" todt="2018-02-13 00:00:00" type="media" scheduleid="0" 
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->widget->widgetId . '"/>
            <stat fromdt="2018-02-14 00:00:00" todt="2018-02-15 00:00:00" type="media" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->widget2->widgetId . '"/>
            <stat fromdt="2018-02-12 00:00:00" todt="2018-02-15 00:00:00" type="widget" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->textWidget->widgetId . '"/>
            <stat fromdt="2018-02-12 00:00:00" todt="2018-02-15 00:00:00" type="event" scheduleid="0" 
                layoutid="0" tag="'.$eventName.'"/>
            <stat fromdt="2018-02-15 00:00:00" todt="2018-02-16 00:00:00" type="layout" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '"/>
            <stat fromdt="2018-02-13 00:00:00" todt="2018-02-14 00:00:00" type="media" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->widget->widgetId . '"/>
            <stat fromdt="2018-02-15 00:00:00" todt="2018-02-16 00:00:00" type="media" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->widget2->widgetId . '"/>
            <stat fromdt="2018-02-15 00:00:00" todt="2018-02-16 00:00:00" type="widget" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->textWidget->widgetId . '"/>
            <stat fromdt="2018-02-15 00:00:00" todt="2018-02-16 00:00:00" type="event" scheduleid="0"
                layoutid="0" tag="'.$eventName.'"/>
            <stat fromdt="2018-02-16 00:00:00" todt="2018-02-17 00:00:00" type="layout" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '"/>
            <stat fromdt="2018-02-16 00:00:00" todt="2018-02-16 12:00:00" type="media" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->widget2->widgetId . '"/>
            <stat fromdt="2018-02-16 12:00:00" todt="2018-02-17 00:00:00" type="media" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->widget->widgetId . '"/>
            <stat fromdt="2018-02-16 00:00:00" todt="2018-02-17 00:00:00" type="widget" scheduleid="0"
                layoutid="' . $this->layout->layoutId . '" mediaid="' . $this->textWidget->widgetId . '"/>
            <stat fromdt="2018-02-16 00:00:00" todt="2018-02-17 00:00:00" type="event" scheduleid="0" 
                layoutid="0" tag="'.$eventName.'"/>
        </stats>');
        $this->assertSame(true, $response);

        $this->getLogger()->debug('Finished Setup');
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the Layout we've been working with
        $this->deleteLayout($this->layout);

        // Delete the Display
        $this->deleteDisplay($this->display);

        // Delete the media records
        $this->media->deleteAssigned();
        $this->media2->deleteAssigned();

        // Delete stat records
        self::$container->get('timeSeriesStore')->deleteStats(Carbon::now(), Carbon::createFromFormat("Y-m-d H:i:s", '2018-02-12 00:00:00'));
    }

    /**
     * Test the method call with default values
     * @group broken
     */
    public function testListAll()
    {
        $response = $this->sendRequest('GET','/stats');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }


    /**
     * Check if proof of play statistics are correct for all types
     * @group broken
     *
     * TODO: I've no idea what we were trying to test here - this needs to be improved or removed.
     * I believe the intention here was to test that the stats submitted are accurate and have been submitted
     * correctly?
     */
    public function testProof()
    {
        // Get stats and see if they match with what we expect
        $response = $this->sendRequest('GET','/stats', [
            'fromDt' => '2018-02-12 00:00:00',
            'toDt' => '2018-02-17 00:00:00',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId]
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $stats = (new XiboStats($this->getEntityProvider()))->get([
            'fromDt' => '2018-02-12 00:00:00',
            'toDt' => '2018-02-17 00:00:00',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId]
        ]);
        $this->assertNotEquals(0, count($stats));
    }

    /**
     * Check if proof of play statistics can be exported
     */
    public function testExport()
    {
        $response = $this->sendRequest('GET','/stats/export', [
            'fromDt' => '2018-02-12 00:00:00',
            'toDt' => '2018-02-15 00:00:00'
        ]);

        // Check 200
        $this->assertSame(200, $response->getStatusCode());

        // We're expecting a send file header as we're testing within Docker
        $this->assertTrue($response->hasHeader('X-Sendfile'));
        $this->assertSame('text/csv', $response->getHeader('Content-Type')[0] ?? '');
        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0] ?? 0);

        // We can't test the body, because there isn't any web server involved with this request.
    }
}
