<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ReportScheduleDataTest
 * @package Xibo\Tests\Integration
 */
class ReportScheduleDataTest extends LocalWebTestCase
{
    use LayoutHelperTrait, DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboDisplay */
    protected $display;

    // Stat type
    private $type;

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

        $this->type = 'layout';
        $hardwareId = $this->display->license;

        // Record some stats
        $this->getXmdsWrapper()->SubmitStats(
            $hardwareId,
            '<stats>
                    <stat fromdt="'. Carbon::now()->startOfDay()->subDays(4)->format(DateFormatHelper::getSystemFormat()) . '" 
                          todt="'.Carbon::now()->startOfDay()->subDays(3)->format(DateFormatHelper::getSystemFormat()) .'"
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                    <stat fromdt="'. Carbon::now()->startOfDay()->subDays(3)->format(DateFormatHelper::getSystemFormat()) . '" 
                          todt="'.Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat()) .'"
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                    <stat fromdt="'. Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat()) . '" 
                          todt="'.Carbon::now()->startOfDay()->subDays()->format(DateFormatHelper::getSystemFormat()) .'"
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                    <stat fromdt="'. Carbon::now()->startOfDay()->subDays()->format(DateFormatHelper::getSystemFormat()) . '" 
                          todt="'.Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()) .'"
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                    </stats>'
        );

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
    public function testProofOfPlayReportYesterday()
    {
        $response = $this->sendRequest('GET', '/report/data/proofofplayReport', [
            'reportFilter'=> 'yesterday',
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $this->type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->getLogger()->debug('Response code is: ' . $response->getStatusCode());

        $body = $response->getBody();

        $this->getLogger()->debug($body);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($body);
        $object = json_decode($body);
        $this->assertObjectHasAttribute('table', $object, $body);
        $this->assertSame(1, $object->table[0]->numberPlays);
    }
    
    /**
     * Check if proof of play statistics are correct for Proof of play Report
     */
    public function testProofOfPlayReport()
    {
        $response = $this->sendRequest('GET', '/report/data/proofofplayReport', [
            'statsFromDt' => Carbon::now()->startOfDay()->subDays(3)->format(DateFormatHelper::getSystemFormat()),
            'statsToDt' => Carbon::now()->startOfDay()->subDays(2)->format(DateFormatHelper::getSystemFormat()),
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $this->type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->getLogger()->debug($body);
        $this->assertNotEmpty($body);
        $object = json_decode($body);
        $this->assertObjectHasAttribute('table', $object, $response->getBody());
        $this->assertSame(1, $object->table[0]->numberPlays);
    }

    /**
     * Check if proof of play statistics are correct for Summary Report
     */
    public function testSummaryReport()
    {
        $response = $this->sendRequest('GET', '/report/data/summaryReport', [
            'statsFromDt' => Carbon::now()->startOfDay()->subDays(4)->format(DateFormatHelper::getSystemFormat()),
            'statsToDt' => Carbon::now()->startOfDay()->format(DateFormatHelper::getSystemFormat()),
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => $this->layout->layoutId,
            'type' => $this->type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('chart', $object, $response->getBody());
        $expectedSeconds =  Carbon::now()->startOfDay()->subDays(3)->format('U') -
            Carbon::now()->startOfDay()->subDays(4)->format('U');
        $this->assertSame($expectedSeconds, $object->chart->data->datasets[0]->data[0]);
    }
}
