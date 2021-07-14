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
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsWidgetTest
 * @package Xibo\Tests\Integration
 */
class StatisticsWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait, DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    /** @var \Xibo\OAuth2\Client\Entity\XiboWidget */
    private $widget;

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

        // Checkout our Layout and add some Widgets to it.
        $layout = $this->getDraft($this->layout);

        // Create and assign new text widget
        $response = $this->getEntityProvider()->post('/playlist/widget/text/' . $layout->regions[0]->regionPlaylist->playlistId);

        $response = $this->getEntityProvider()->put('/playlist/widget/' . $response['widgetId'], [
            'text' => 'Widget A',
            'duration' => 100,
            'useDuration' => 1
        ]);

        $this->widget = (new XiboText($this->getEntityProvider()))->hydrate($response);

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

        // Delete stat records
        self::$container->get('timeSeriesStore')->deleteStats(Carbon::now(), Carbon::now()->startOfDay()->subDays(10));
    }

    /**
     * Check if proof of play statistics are correct
     */
    public function testProof()
    {
        $type = 'widget';

        $hardwareId = $this->display->license;

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(5)->format(DateFormatHelper::getSystemFormat()) . '" 
                        todt="'.Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat()) .'" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" 
                        mediaid="'.$this->widget->widgetId.'"/>
                    </stats>'
        );
        $this->assertSame(true, $response);

        // Second insert
        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat()) . '" 
                        todt="'.Carbon::now()->startOfDay()->subDays(1)->format(DateFormatHelper::getSystemFormat()) .'" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" 
                        mediaid="'.$this->widget->widgetId.'"/>
                    </stats>'
        );
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(1)->format(DateFormatHelper::getSystemFormat()) .'" 
                        todt="'. Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()) .'" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" 
                        mediaid="'.$this->widget->widgetId.'"/>
                    </stats>'
        );
        $this->assertSame(true, $response);

        // Get stats and see if they match with what we expect
        $response = $this->sendRequest('GET', '/stats', [
            'fromDt' => Carbon::now()->startOfDay()->subDays(5)->format(DateFormatHelper::getSystemFormat()),
            'toDt' => Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()),
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $type
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertEquals(3, $object->data->recordsTotal);
        $this->assertCount(3, $object->data->data);
    }
}
