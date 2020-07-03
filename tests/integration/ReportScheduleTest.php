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

use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboReportSchedule;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ReportScheduleTest
 * @package Xibo\Tests\Integration
 */
class ReportScheduleTest extends LocalWebTestCase
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

        // Delete Report Schedule
        $reportSchedule->delete();
    }

    /**
     *  Report Schedule Delete All Saved Report
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function testReportScheduleDeleteAllSavedReport()
    {
        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', 'proofofplayReport', 'daily');

        $reportScheduleId = $reportSchedule->reportScheduleId;

        $task = $this->getTask('\Xibo\XTR\ReportScheduleTask');
        $task->run();
        self::$container->get('store')->commitIfNecessary();

        // Delete All Saved Report
        $resDelete = $this->sendRequest('POST', '/report/reportschedule/' .
            $reportScheduleId. '/deletesavedreport');
        $this->assertSame(200, $resDelete->getStatusCode(), $resDelete->getBody());

        // Delete Report Schedule
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

        // Delete Report Schedule
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

        // Delete Report Schedule
        $reportSchedule->delete();
    }

    /**
     * Delete Saved Report
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException|\Xibo\Support\Exception\NotFoundException
     */
    public function testDeleteSavedReport()
    {
        $reportSchedule = (new XiboReportSchedule($this->getEntityProvider()))
            ->create('Report Schedule', 'proofofplayReport', 'daily');

        // Create a saved report
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
