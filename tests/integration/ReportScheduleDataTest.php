<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
        $yesterday = Carbon::parse()->subDay()->startOfDay();
        $today = Carbon::parse()->startOfDay();

        // Record some stats
        $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-03-31 00:00:00" 
                        todt="2020-04-01 00:00:00" 
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                        <stat fromdt="2020-04-01 00:00:00" 
                        todt="2020-04-02 00:00:00" 
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                        <stat fromdt="2020-04-02 00:00:00" 
                        todt="2020-04-03 00:00:00" 
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                        <stat fromdt="'.$yesterday.'"
                        todt="'.$today.'"
                        type="'.$this->type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                    </stats>');

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
        self::$container->get('timeSeriesStore')->deleteStats(Carbon::now(), Carbon::createFromFormat("Y-m-d H:i:s", '2020-03-31 00:00:00'));
    }

    /**
     * Check if proof of play statistics are correct
     */
    public function testProofOfPlayReportYesterday()
    {

        $response = $this->sendRequest('GET','/report/data/proofofplayReport', [
            'reportFilter'=> 'yesterday',
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $this->type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(1,  $object->data[0]->numberPlays);
    }
    
    /**
     * Check if proof of play statistics are correct for Proof of play Report
     */
    public function testProofOfPlayReport()
    {
        $response = $this->sendRequest('GET','/report/data/proofofplayReport', [
            'statsFromDt' => Carbon::parse('2020-03-31 00:00:00'),
            'statsToDt' => Carbon::parse('2020-04-01 00:00:00'),
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $this->type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(2,  $object->data[0]->numberPlays);
    }

    /**
     * Check if proof of play statistics are correct for Summary Report
     */
    public function testSummaryReport()
    {
        $response = $this->sendRequest('GET','/report/data/summaryReport', [
            'statsFromDt' => Carbon::parse('2020-03-31 00:00:00'),
            'statsToDt' => Carbon::parse('2020-04-31 00:00:00'),
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => $this->layout->layoutId,
            'type' => $this->type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(86400, $object->extra->durationData[0]);
    }
}
