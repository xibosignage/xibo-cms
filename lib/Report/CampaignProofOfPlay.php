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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class CampaignProofOfPlay
 * @package Xibo\Report
 */
class CampaignProofOfPlay implements ReportInterface
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
    public function getReportEmailTemplate()
    {
        return 'campaign-proofofplay-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'campaign-proofofplay-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'campaign-proofofplay-report-form',
            'campaignProofOfPlay',
            'Connector Reports',
            [
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
            ],
            __('Select a display')
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data = [];

        $data['hiddenFields'] =  '';
        $data['reportName'] = 'campaignProofOfPlay';

        return [
            'template' => 'campaign-proofofplay-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $filterCriteria = [
            'filter' => $filter,
            'displayId' => $sanitizedParams->getInt('displayId'),
            'displayIds' => $sanitizedParams->getIntArray('displayIds'),
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
        } elseif ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
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

        $displayId = $sanitizedParams->getInt('displayId');
        if (!empty($displayId)) {
            // Get display
            try {
                $displayName = $this->displayFactory->getById($displayId)->display;
                $saveAs .= '(Display: '. $displayName . ')';
            } catch (NotFoundException $error) {
                $saveAs .= '(DisplayId: Not Found )';
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
            $json['recordsTotal']
        );
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $parentCampaignId = $sanitizedParams->getInt('parentCampaignId');

        // Get campaign
        if (!empty($parentCampaignId)) {
            $campaign = $this->campaignFactory->getById($parentCampaignId);
        }

        // Display filter.
        try {
            // Get an array of display id this user has access to.
            $displayIds = $this->getDisplayIdFilter($sanitizedParams);
        } catch (GeneralException $exception) {
            // stop the query
            return new ReportResult();
        }

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determines whether the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

        switch ($reportFilter) {
            case 'today':
                $fromDt = $now->copy()->startOfDay();
                $toDt = $fromDt->copy()->addDay();
                break;

            case 'yesterday':
                $fromDt = $now->copy()->startOfDay()->subDay();
                $toDt = $now->copy()->startOfDay();
                break;

            case 'thisweek':
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek();
                $toDt = $fromDt->copy()->addWeek();
                break;

            case 'thismonth':
                $fromDt = $now->copy()->startOfMonth();
                $toDt = $fromDt->copy()->addMonth();
                break;

            case 'thisyear':
                $fromDt = $now->copy()->startOfYear();
                $toDt = $fromDt->copy()->addYear();
                break;

            case 'lastweek':
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek()->subWeek();
                $toDt = $fromDt->copy()->addWeek();
                break;

            case 'lastmonth':
                $fromDt = $now->copy()->startOfMonth()->subMonth();
                $toDt = $fromDt->copy()->addMonth();
                break;

            case 'lastyear':
                $fromDt = $now->copy()->startOfYear()->subYear();
                $toDt = $fromDt->copy()->addYear();
                break;

            case '':
            default:
                // Expect dates to be provided.
                $fromDt = $sanitizedParams->getDate('statsFromDt', ['default' => Carbon::now()->subDay()]);
                $fromDt->startOfDay();

                $toDt = $sanitizedParams->getDate('statsToDt', ['default' => Carbon::now()]);
                $toDt->endOfDay();

                // What if the fromdt and todt are exactly the same?
                // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
                if ($fromDt == $toDt) {
                    $toDt->addDay();
                }

                break;
        }

        $params = [
            'campaignId' => $parentCampaignId,
            'displayIds' => $displayIds,
            'groupBy' => $sanitizedParams->getString('groupBy')
        ];

        // when the reportfilter is wholecampaign take campaign start/end as form/to date
        if (!empty($parentCampaignId) && $sanitizedParams->getString('reportFilter') === 'wholecampaign') {
            $params['fromDt'] = !empty($campaign->getStartDt()) ? $campaign->getStartDt()->format('Y-m-d H:i:s') : null;
            $params['toDt'] = !empty($campaign->getEndDt()) ? $campaign->getEndDt()->format('Y-m-d H:i:s') : null;

            if (empty($campaign->getStartDt()) || empty($campaign->getEndDt())) {
                return new ReportResult();
            }
        } else {
            $params['fromDt'] = $fromDt->format('Y-m-d H:i:s');
            $params['toDt'] =  $toDt->format('Y-m-d H:i:s');
        }

        // --------
        // ReportDataEvent
        $event = new ReportDataEvent('campaignProofofplay');

        // Set query params for audience proof of play report
        $event->setParams($params);

        // Dispatch the event - listened by Audience Report Connector
        $this->dispatcher->dispatch($event, ReportDataEvent::$NAME);
        $results = $event->getResults();

        $result['periodStart'] = $params['fromDt'];
        $result['periodEnd'] = $params['toDt'];

        // Sanitize results??
        $rows = [];
        foreach ($results['json'] as $row) {
            $entry = [];

            $entry['labelDate'] = $row['labelDate'];
            $entry['adPlays'] = $row['adPlays'];
            $entry['adDuration'] = $row['adDuration'];
            $entry['impressions'] = $row['impressions'];
            $entry['spend'] = $row['spend'];

            $rows[] = $entry;
        }

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
            [],
            $results['error'] ?? null
        );
    }
}
