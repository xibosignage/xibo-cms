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
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class StatisticsEventTest
 * @package Xibo\Tests\Integration
 */
class StatisticsEventTest extends LocalWebTestCase
{
    use LayoutHelperTrait, DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

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
        self::$container->get('timeSeriesStore')
            ->deleteStats(Carbon::now(), Carbon::now()->startOfDay()->subDays(10));
    }

    /**
     * Check if proof of play statistics are correct
     */
    public function testProof()
    {
        $type = 'event';

        // Checkout layout
        $layout = $this->getDraft($this->layout);

        $hardwareId = $this->display->license;

        // One word name for the event
        $eventName = Random::generateString(10, 'event');

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(5)->format(DateFormatHelper::getSystemFormat()) . '" 
                        todt="'.Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat()) .'" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="0" 
                        tag="'.$eventName.'"/>
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
                        layoutid="0"
                        tag="'.$eventName.'"/>
                    </stats>'
        );
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                        <stat fromdt="'. Carbon::now()->startOfDay()->subDays(1)->format(DateFormatHelper::getSystemFormat()) . '" 
                        todt="'.Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()) .'" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="0"
                        tag="'.$eventName.'"/>
                    </stats>'
        );
        $this->assertSame(true, $response);

        // Get stats and see if they match with what we expect
        $response = $this->sendRequest('GET', '/stats', [
            'fromDt' => Carbon::now()->startOfDay()->subDays(5)->format(DateFormatHelper::getSystemFormat()),
            'toDt' => Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()),
            'displayId' => $this->display->displayId,
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
