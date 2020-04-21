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
use Jenssegers\Date\Date;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboReportSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;
use Xibo\XTR\TaskInterface;

/**
 * Class ReportScheduleTest
 * @package Xibo\Tests\Integration
 */
class ReportTest extends LocalWebTestCase
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
        $type = 'layout';

        $hardwareId = $this->display->license;

        $yesterday = Carbon::parse()->subDay()->startOfDay();
        $today = Carbon::parse()->startOfDay();

        // First insert for Yesterday
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="'.$yesterday.'"
                        todt="'.$today.'"
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                    </stats>');
        $this->assertSame(true, $response);

        $response = $this->sendRequest('GET','/report/data/proofofplayReport', [
            'reportFilter'=> 'yesterday',
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(1,  $object->data[0]->numberPlays);
    }
    
    /**
     * Check if proof of play statistics are correct for Summary Report
     */
    public function testSummaryReport()
    {
        $type = 'layout';

        $hardwareId = $this->display->license;

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-03-31 00:00:00" 
                        todt="2020-04-01 00:00:00" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                    </stats>');
        $this->assertSame(true, $response);

        // Second insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-04-01 00:00:00" 
                        todt="2020-04-02 00:00:00" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-04-02 00:00:00" 
                        todt="2020-04-03 00:00:00" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                    </stats>');
        $this->assertSame(true, $response);


        $response = $this->sendRequest('GET','/report/data/summaryReport', [
            'statsFromDt' => Carbon::parse('2020-03-31 00:00:00'),
            'statsToDt' => Carbon::parse('2020-04-31 00:00:00'),
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => $this->layout->layoutId,
            'type' => $type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(86400, $object->extra->durationData[0]);
    }
    /**
     * Check if proof of play statistics are correct for Proof of play Report
     */
    public function testProofOfPlayReport()
    {
        $type = 'layout';

        $hardwareId = $this->display->license;

        // First insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-03-31 00:00:00" 
                        todt="2020-04-01 00:00:00" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'" />
                    </stats>');
        $this->assertSame(true, $response);

        // Second insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-04-01 00:00:00" 
                        todt="2020-04-02 00:00:00" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                    </stats>');
        $this->assertSame(true, $response);

        // Third insert
        $response = $this->getXmdsWrapper()->SubmitStats($hardwareId,
            '<stats>
                        <stat fromdt="2020-04-02 00:00:00" 
                        todt="2020-04-03 00:00:00" 
                        type="'.$type.'" 
                        scheduleid="0" 
                        layoutid="'.$this->layout->layoutId.'"/>
                    </stats>');
        $this->assertSame(true, $response);


        $response = $this->sendRequest('GET','/report/data/proofofplayReport', [
            'statsFromDt' => Carbon::parse('2020-03-31 00:00:00'),
            'statsToDt' => Carbon::parse('2020-04-31 00:00:00'),
            'groupByFilter' => 'byday',
            'displayId' => $this->display->displayId,
            'layoutId' => [$this->layout->layoutId],
            'type' => $type
        ], ['HTTP_ACCEPT'=>'application/json'], 'web', true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(3,  $object->data[0]->numberPlays);
    }


    /**
     * Each array is a test run
     * Format (filter, reportName)
     * @return array
     */
    public function filterCreateCases()
    {
        return [
            'proofofplayReport Daily' => ['daily', 'proofofplayReport'],
            'proofofplayReport Weekly' => ['weekly', 'proofofplayReport'],
            'proofofplayReport Monthly' => ['monthly', 'proofofplayReport'],
            'proofofplayReport Yearly' => ['yearly', 'proofofplayReport'],
            'summaryReport Daily' => ['daily', 'summaryReport'],
            'summaryReport Weekly' => ['weekly', 'summaryReport'],
            'summaryReport Monthly' => ['monthly', 'summaryReport'],
            'summaryReport Yearly' => ['yearly', 'summaryReport'],
            'distributionReport Daily' => ['daily', 'distributionReport'],
            'distributionReport Weekly' => ['weekly', 'distributionReport'],
            'distributionReport Monthly' => ['monthly', 'distributionReport'],
            'distributionReport Yearly' => ['yearly', 'distributionReport'],
        ];
    }

    /**
     * Create Report Schedule
     * @dataProvider filterCreateCases
     */
    public function testCreateReportSchedule($filter, $report)
    {
        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', $report, $filter, 'byhour', null,
                $this->display->displayId, '{"type":"layout","selectedId":'.$this->layout->layoutId.',"eventTag":null}');

        $this->assertSame($report, $reportSchedule->reportName);

        $this->setService();

        $reportSchedule->delete();
    }

    /**
     *  Report Schedule Delete All Saved Report
     */
    public function testReportScheduleDeleteAllSavedReport()
    {
        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', 'proofofplayReport', 'daily');

        $reportScheduleId = $reportSchedule->reportScheduleId;

        /** @var TaskInterface $task */
        $task = $this->getTask('\Xibo\XTR\ReportScheduleTask');
        $task->run();
        self::$container->get('store')->commitIfNecessary();

        // Delete All Saved Report
        $resDelete = $this->sendRequest('POST', '/report/reportschedule/' .
            $reportScheduleId. '/deletesavedreport',[
            'disableUserCheck' => 1,
        ]);
        $this->assertSame(200, $resDelete->getStatusCode(), $resDelete->getBody());

        $reportSchedule->delete();
    }


    /**
     *  Report Schedule Toggle Active
     */
    public function testReportScheduleToggleActive()
    {
        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', 'proofofplayReport', 'daily');

        // Toggle Active
        $response = $this->sendRequest('POST', '/report/reportschedule/'.
            $reportSchedule->reportScheduleId.'/toggleactive');
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame('Paused Report Schedule', $object->message);

        $reportSchedule->delete();
    }


    /**
     *  Report Schedule Reset
     */
    public function testReportScheduleReset()
    {

        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', 'proofofplayReport', 'daily');


        // Reset
        $response = $this->sendRequest('POST', '/report/reportschedule/'.
            $reportSchedule->reportScheduleId.'/reset');
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame('Success', $object->message);



        $reportSchedule->delete();
    }


    /**
     *  Delete Saved Report
     */
    public function testDeleteSavedReport()
    {

        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', 'proofofplayReport', 'daily');

        // Create a saved report
        /** @var TaskInterface $task */
        $task = $this->getTask('\Xibo\XTR\ReportScheduleTask');
        $task->run();
        self::$container->get('store')->commitIfNecessary();

        // Get updated report schedule's last saved report Id
        $rs = (new XiboReportSchedule($this->getEntityProvider()))
            ->getById($reportSchedule->reportScheduleId);

        // Delete Saved Report
        $response = $this->sendRequest('DELETE', '/report/savedreport/'.
            $rs->lastSavedReportId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());

        // Delete Report Schedule
        $rs->delete();
    }
}
