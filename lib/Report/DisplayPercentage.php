<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
namespace Xibo\Report;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Controller\DataTablesDotNetTrait;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Event\ReportDataEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class DisplayPercentage
 * @package Xibo\Report
 */
class DisplayPercentage implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var ReportScheduleFactory
     */
    private $reportScheduleFactory;

    /**
     * @var SanitizerService
     */
    private $sanitizer;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var ApplicationState
     */
    private $state;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->campaignFactory = $container->get('campaignFactory');
        $this->displayFactory = $container->get('displayFactory');
        $this->reportScheduleFactory = $container->get('reportScheduleFactory');
        $this->sanitizer = $container->get('sanitizerService');
        $this->dispatcher = $container->get('dispatcher');
        return $this;
    }

    /** @inheritdoc */
    public function getReportChartScript($results)
    {
        return json_encode($results->chart);
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'display-percentage-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'display-percentage-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'display-percentage-report-form',
            'displayPercentage',
            'Connector Reports',
            [
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ],
            __('Select a campaign')
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data = [];

        $data['hiddenFields'] = json_encode([
            'parentCampaignId' => $sanitizedParams->getInt('parentCampaignId')
        ]);
        $data['reportName'] = 'displayPercentage';

        return [
            'template' => 'display-percentage-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $hiddenFields = json_decode($sanitizedParams->getString('hiddenFields'), true);

        $filterCriteria = [
            'filter' => $filter,
            'parentCampaignId' => $hiddenFields['parentCampaignId']
        ];

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';
        } elseif ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
        } elseif ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupByFilter'] = 'byweek';
        } elseif ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupByFilter'] = 'bymonth';
        }

        $filterCriteria['sendEmail'] = $sanitizedParams->getCheckbox('sendEmail');
        $filterCriteria['nonusers'] = $sanitizedParams->getString('nonusers');

        // Return
        return [
            'filterCriteria' => json_encode($filterCriteria),
            'schedule' => $schedule
        ];
    }

    /** @inheritdoc */
    public function generateSavedReportName(SanitizerInterface $sanitizedParams)
    {
        $saveAs = sprintf(__('%s report for ', ucfirst($sanitizedParams->getString('filter'))));

        $parentCampaignId = $sanitizedParams->getInt('parentCampaignId');
        if (!empty($parentCampaignId)) {
            // Get display
            try {
                $parentCampaignName = $this->campaignFactory->getById($parentCampaignId)->campaign;
                $saveAs .= '(Campaign: '. $parentCampaignName . ')';
            } catch (NotFoundException $error) {
                $saveAs .= '(Campaign: Not Found )';
            }
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        return [
            'periodStart' => $result['periodStart'],
            'periodEnd' => $result['periodEnd'],
            'table' => $result['result'],
        ];
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Get filter criteria
        $rs = $this->reportScheduleFactory->getById($savedReport->reportScheduleId, 1)->filterCriteria;
        $filterCriteria = json_decode($rs, true);

        // Show filter criteria
        $metadata = [];

        // Get Meta data
        $metadata['periodStart'] = $json['metadata']['periodStart'];
        $metadata['periodEnd'] = $json['metadata']['periodEnd'];
        $metadata['generatedOn'] = Carbon::createFromTimestamp($savedReport->generatedOn)
            ->format(DateFormatHelper::getSystemFormat());
        $metadata['title'] = $savedReport->saveAs;

        // Report result object
        return new ReportResult(
            $metadata,
            $json['table'],
            $json['recordsTotal'],
            $json['chart']
        );
    }

    /** @inheritDoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $params = [
            'parentCampaignId' => $sanitizedParams->getInt('parentCampaignId')
        ];

        // --------
        // ReportDataEvent
        $event = new ReportDataEvent('displayPercentage');

        // Set query params for report
        $event->setParams($params);

        // Dispatch the event - listened by Audience Report Connector
        $this->dispatcher->dispatch($event, ReportDataEvent::$NAME);
        $results = $event->getResults();

        // TODO
        $result['periodStart'] = Carbon::now()->format('Y-m-d H:i:s');
        $result['periodEnd'] = Carbon::now()->format('Y-m-d H:i:s');

        $rows = [];
        $displayCache = [];

        foreach ($results['json'] as $row) {
            // ----
            // Build Chart data

            // ----
            // Build Tabular data
            $entry = [];

            // --------
            // Get Display
            try {
                if (!array_key_exists($row['displayId'], $displayCache)) {
                    $display = $this->displayFactory->getById($row['displayId']);
                    $displayCache[$row['displayId']] = $display->display;
                }
                $entry['label'] = $displayCache[$row['displayId']] ?? '';
            } catch (\Exception $e) {
                $entry['label'] = __('Not found');
            }

            $entry['spendData'] = $row['spendData'];
            $entry['playtimeDuration'] = $row['playtimeDuration'];
            $entry['backgroundColor'] = '#'.substr(md5($row['displayId']), 0, 6);

            $rows[] = $entry;
        }

        // Build Chart to pass in twig file chart.js
        $chart = [];

        // Set Meta data
        $metadata = [
            'periodStart' => $result['periodStart'],
            'periodEnd' => $result['periodEnd'],
        ];

        $recordsTotal = count($rows);

        // ----
        // Table Only
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            $metadata,
            $rows,
            $recordsTotal,
            $chart,
            $results['error'] ?? null
        );
    }
}
