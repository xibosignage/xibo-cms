<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Tests\Integration;

use Jenssegers\Date\Date;
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
        self::$container->timeSeriesStore->deleteStats(Date::now(), Date::now()->startOfDay()->subDays(5));
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

        $hardwareId = $this->display->license;

        // One word name for the event
        $eventName = Random::generateString(10, 'event');

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') .'" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" />
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(4)->format('Y-m-d H:i:s') .'" 
                        type="media" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" 
                        mediaid="' . $this->widget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(3)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') .'" 
                        type="media"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->widget2->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') .'" 
                        type="widget"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->textWidget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') .'" 
                        type="event"
                        scheduleid="0"
                        layoutid="0"
                        tag="'.$eventName.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Second insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(1)->format('Y-m-d H:i:s') .'" 
                        type="layout"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(4)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(3)->format('Y-m-d H:i:s') .'" 
                        type="media"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->widget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(1)->format('Y-m-d H:i:s') .'" 
                        type="media"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->widget2->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(1)->format('Y-m-d H:i:s') .'" 
                        type="widget"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->textWidget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(1)->format('Y-m-d H:i:s') .'" 
                        type="event"
                        scheduleid="0"
                        layoutid="0"
                        tag="'.$eventName.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(1)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->format('Y-m-d H:i:s') .'" 
                        type="layout"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subHours(12)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->format('Y-m-d H:i:s') .'" 
                        type="media"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->widget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDay()->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subHours(12)->format('Y-m-d H:i:s') .'" 
                        type="media"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->widget2->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDay()->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->format('Y-m-d H:i:s') .'" 
                        type="widget"
                        scheduleid="0"
                        layoutid="' . $this->layout->layoutId . '"
                        mediaid="' . $this->textWidget->widgetId . '"/>
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDay()->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->format('Y-m-d H:i:s') .'" 
                        type="event"
                        scheduleid="0"
                        layoutid="0"
                        tag="'.$eventName.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Get stats and see if they match with what we expect
        $this->client->get('/stats', [
            'fromDt' => Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s'),
            'toDt' => Date::now()->startOfDay()->format('Y-m-d H:i:s'),
            'displayId' => $this->display->displayId
        ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        // $this->getLogger()->debug($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $stats = (new XiboStats($this->getEntityProvider()))->get(['fromDt' => Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s'), 'toDt' => Date::now()->startOfDay()->format('Y-m-d H:i:s'), 'layoutId' => $this->layout->layoutId]);
        // print_r($stats);
        $this->assertNotEquals(0, count($stats));
    }

    /**
     * Check if proof of play statistics can be exported
     */
    public function testExport()
    {
        $hardwareId = $this->display->license;

        // One insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s') .'" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" />
                    </stats>');
        $this->assertSame(true, $response);

        $this->client->get('/stats/export', [
            'fromDt' => Date::now()->startOfDay()->subDays(5)->format('Y-m-d H:i:s'),
            'toDt' => Date::now()->startOfDay()->subDays(2)->format('Y-m-d H:i:s'),
        ]);
        $this->assertSame(200, $this->client->response->status());

        $body = $this->client->response->body();
        $this->assertContains('layout,"'. Date::now()->startOfDay()->subDays(5)->format('Y-m-d'), $body);
    }

    public function testProofOldStats()
    {
        $hardwareId = $this->display->license;

        // Attempt to insert stat data older than 30 days
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(35)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(31)->format('Y-m-d H:i:s') .'" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" />
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'. Date::now()->startOfDay()->subDays(40)->format('Y-m-d H:i:s') . '" 
                        todt="'.Date::now()->startOfDay()->subDays(38)->format('Y-m-d H:i:s') .'" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" />
                    </stats>');
        $this->assertSame(true, $response);

        // Default max stat age is 30 days, therefore we expect to get no results after the attempted inserts.
        $stats = (new XiboStats($this->getEntityProvider()))->get(['fromDt' => Date::now()->startOfDay()->subDays(41)->format('Y-m-d H:i:s'), 'toDt' => Date::now()->startOfDay()->subDays(31)->format('Y-m-d H:i:s'), 'layoutId' => $this->layout->layoutId]);
        // print_r($stats);
        $this->assertEquals(0, count($stats));
    }
}
