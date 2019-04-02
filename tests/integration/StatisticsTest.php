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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboRegion;
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
        self::$container->timeSeriesStore->deleteStats(date("Y-m-d H:i:s"), '2018-02-12');
    }

    /**
     * Test the method call with default values
     * @group broken
     */
    public function testListAll()
    {
        $this->client->get('/stats');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }


    /**
     * Check if proof of play statistics are correct for all types
     */
    public function testProof()
    {
        // Checkout layout
        $layout = $this->checkout($this->layout);

        $hardwareId = $this->display->license;

        // Add another region
        $region = $layout->regions[0];
        $region2 = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 100, 100, 475, 425);

        # Create and assign new text widget
        $text = (new XiboText($this->getEntityProvider()))->create('Text item', 10, 1, 'marqueeRight', 5, null, null, 'TEST API TEXT', null, $region2->regionPlaylist->playlistId);

        # Assign media to a playlists
        $playlist = (new XiboPlaylist($this->getEntityProvider()))->assign([$this->media->mediaId, $this->media2->mediaId], 10, $region->regionPlaylist->playlistId);

        # Get Widget Id
        $widget = $playlist->widgets[0];
        $widget2 = $playlist->widgets[1];

        // One word name for the event
        $eventName = Random::generateString(10, 'event');

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-12 00:00:00" 
                        todt="2018-02-15 00:00:00" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '" />
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-12 00:00:00" 
                        todt="2018-02-13 00:00:00" 
                        type="media" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '" 
                        mediaid="' . $widget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-14 00:00:00"
                        todt="2018-02-15 00:00:00"
                        type="media"
                        scheduleid="0"
                        layoutid="' . $layout->layoutId . '"
                        mediaid="' . $widget2->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-12 00:00:00" 
                        todt="2018-02-15 00:00:00" 
                        type="widget" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '" 
                        mediaid="' . $text->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-12 00:00:00" 
                        todt="2018-02-15 00:00:00" 
                        type="event" 
                        scheduleid="0" 
                        layoutid="'.$layout->layoutId.'" 
                        tag="'.$eventName.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Second insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-15 00:00:00" 
                        todt="2018-02-16 00:00:00" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-13 00:00:00"
                        todt="2018-02-14 00:00:00"
                        type="media"
                        scheduleid="0"
                        layoutid="' . $layout->layoutId . '"
                        mediaid="' . $widget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-15 00:00:00"
                        todt="2018-02-16 00:00:00"
                        type="media"
                        scheduleid="0"
                        layoutid="' . $layout->layoutId . '"
                        mediaid="' . $widget2->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-15 00:00:00" 
                        todt="2018-02-16 00:00:00" 
                        type="widget" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '" 
                        mediaid="' . $text->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-15 00:00:00" 
                        todt="2018-02-16 00:00:00" 
                        type="event" 
                        scheduleid="0" 
                        layoutid="'.$layout->layoutId.'"
                        tag="'.$eventName.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-16 00:00:00" 
                        todt="2018-02-17 00:00:00" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-16 12:00:00"
                        todt="2018-02-17 00:00:00"
                        type="media"
                        scheduleid="0"
                        layoutid="' . $layout->layoutId . '"
                        mediaid="' . $widget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-16 00:00:00"
                        todt="2018-02-16 12:00:00"
                        type="media"
                        scheduleid="0"
                        layoutid="' . $layout->layoutId . '"
                        mediaid="' . $widget2->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-16 00:00:00" 
                        todt="2018-02-17 00:00:00" 
                        type="widget" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '" 
                        mediaid="' . $text->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-16 00:00:00" 
                        todt="2018-02-17 00:00:00" 
                        type="event" 
                        scheduleid="0" 
                        layoutid="'.$layout->layoutId.'"
                        tag="'.$eventName.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Get stats and see if they match with what we expect
        $this->client->get('/stats', [
            'fromDt' => '2018-02-12 00:00:00',
            'toDt' => '2018-02-17 00:00:00',
            'displayId' => $this->display->displayId
        ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        // $this->getLogger()->debug($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $stats = (new XiboStats($this->getEntityProvider()))->get(['fromDt' => '2018-02-12 00:00:00', 'toDt' => '2018-02-17 00:00:00', 'layoutId' => $layout->layoutId]);
        // print_r($stats);
        $this->assertNotEquals(0, count($stats));
    }

    /**
     * Check if proof of play statistics can be exported
     */
    public function testExport()
    {
        // Checkout layout
        $layout = $this->checkout($this->layout);

        $hardwareId = $this->display->license;

        // One insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2018-02-12 00:00:00" 
                        todt="2018-02-15 00:00:00" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $layout->layoutId . '" />
                    </stats>');
        $this->assertSame(true, $response);

        $this->client->get('/stats/export', [
            'fromDt' => '2018-02-12 00:00:00',
            'toDt' => '2018-02-15 00:00:00'
        ]);
        $this->assertSame(200, $this->client->response->status());

        $body = $this->client->response->body();
        $this->assertContains('layout,"2018-02-12', $body);
    }

}
