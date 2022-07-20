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

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;
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
        self::$container->get('timeSeriesStore')
            ->deleteStats(Carbon::now(), Carbon::now()->startOfDay()->subDays(10));
    }

    /**
     * Test the method call with default values
     */
    public function testListAll()
    {
        $this->getXmdsWrapper()->SubmitStats($this->display->license, '
        <stats>
            <stat fromdt="'. Carbon::now()->startOfDay()->subHours(12)->format(DateFormatHelper::getSystemFormat()) . '" 
                  todt="'.Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()) .'"
                  type="layout"
                  scheduleid="0" 
                  layoutid="' . $this->layout->layoutId . '" />
        </stats>');

        $response = $this->sendRequest('GET', '/stats');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertEquals(1, $object->data->recordsTotal);
        $this->assertCount(1, $object->data->data);
    }

    /**
     * Check if proof of play statistics can be exported
     */
    public function testExport()
    {
        $this->getXmdsWrapper()->SubmitStats($this->display->license, '
            <stats>
                <stat fromdt="'. Carbon::now()->startOfDay()->subDays(4)->format(DateFormatHelper::getSystemFormat()) . '" 
                      todt="'.Carbon::now()->startOfDay()->subDays(3)->format(DateFormatHelper::getSystemFormat()) .'"
                      type="layout"
                      scheduleid="0" 
                      layoutid="' . $this->layout->layoutId . '" />
                <stat fromdt="'. Carbon::now()->startOfDay()->subDays(4)->format(DateFormatHelper::getSystemFormat()) . '" 
                      todt="'.Carbon::now()->startOfDay()->subDays(3)->format(DateFormatHelper::getSystemFormat()) .'" 
                      type="media" 
                      scheduleid="0" 
                      layoutid="' . $this->layout->layoutId . '" 
                      mediaid="' . $this->widget->widgetId . '"/>
                <stat fromdt="'. Carbon::now()->startOfDay()->subDays(4)->format(DateFormatHelper::getSystemFormat()) . '" 
                      todt="'.Carbon::now()->startOfDay()->subDays(3)->format(DateFormatHelper::getSystemFormat()) .'"
                      type="widget"
                      scheduleid="0"
                      layoutid="' . $this->layout->layoutId . '"
                      mediaid="' . $this->textWidget->widgetId . '"/>
            </stats>');


        $response = $this->sendRequest('GET', '/stats/export', [
            'fromDt' => Carbon::now()->startOfDay()->subDays(5)->format(DateFormatHelper::getSystemFormat()),
            'toDt' => Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat())
        ]);

        // Check 200
        $this->assertSame(200, $response->getStatusCode());

        // We're expecting a send file header as we're testing within Docker
        $this->assertTrue($response->hasHeader('X-Sendfile'));
        $this->assertSame('text/csv', $response->getHeader('Content-Type')[0] ?? '');
        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0] ?? 0);

        // We can't test the body, because there isn't any web server involved with this request.
    }

    public function testProofOldStats()
    {
        $hardwareId = $this->display->license;

        // Attempt to insert stat data older than 30 days
        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(35)->format('Y-m-d H:i:s') . '" 
                        todt="'.Carbon::now()->startOfDay()->subDays(31)->format('Y-m-d H:i:s') .'" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" />
                    </stats>'
        );
        $this->assertSame(true, $response);

        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(40)->format('Y-m-d H:i:s') . '" 
                        todt="'.Carbon::now()->startOfDay()->subDays(38)->format('Y-m-d H:i:s') .'" 
                        type="layout" 
                        scheduleid="0" 
                        layoutid="' . $this->layout->layoutId . '" />
                    </stats>'
        );

        $this->assertSame(true, $response);

        // Default max stat age is 30 days, therefore we expect to get no results after the attempted inserts.
        $stats = (new XiboStats($this->getEntityProvider()))->get([
            'fromDt' => Carbon::now()->startOfDay()->subDays(41)->format('Y-m-d H:i:s'),
            'toDt' => Carbon::now()->startOfDay()->subDays(31)->format('Y-m-d H:i:s'),
            'layoutId' => [$this->layout->layoutId],
            'type' => 'layout'
        ]);
        $this->assertEquals(0, count($stats));
    }
}
