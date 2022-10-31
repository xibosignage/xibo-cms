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
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class SummaryReport
 * @package Xibo\Report
 */
class SummaryReport implements ReportInterface
{
    use ReportDefaultTrait;
    use SummaryDistributionCommonTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /**
     * @var SanitizerService
     */
    private $sanitizer;

    private $table = 'stat';

    private $periodTable = 'period';

    /** @inheritDoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->reportService = $container->get('reportService');
        $this->sanitizer = $container->get('sanitizerService');

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
        return 'summary-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'summary-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'summary-report-form',
            'summaryReport',
            'Proof of Play',
            [
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ],
            __('Select a type and an item (i.e., layout/media/tag)')
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $type = $sanitizedParams->getString('type');
        $formParams = $this->getReportScheduleFormTitle($sanitizedParams);

        $data = ['filters' => []];
        $data['filters'][] = ['name'=> 'Daily', 'filter'=> 'daily'];
        $data['filters'][] = ['name'=> 'Weekly', 'filter'=> 'weekly'];
        $data['filters'][] = ['name'=> 'Monthly', 'filter'=> 'monthly'];
        $data['filters'][] = ['name'=> 'Yearly', 'filter'=> 'yearly'];

        $data['formTitle'] = $formParams['title'];

        $data['hiddenFields'] = json_encode([
            'type' => $type,
            'selectedId' => $formParams['selectedId'],
            'eventTag' => $eventTag ?? null
        ]);

        $data['reportName'] = 'summaryReport';

        return [
            'template' => 'summary-report-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $displayId = $sanitizedParams->getInt('displayId');
        $displayGroupIds = $sanitizedParams->getIntArray('displayGroupId', ['default' => []]);
        $hiddenFields = json_decode($sanitizedParams->getString('hiddenFields'), true);

        $type = $hiddenFields['type'];
        $selectedId = $hiddenFields['selectedId'];
        $eventTag = $hiddenFields['eventTag'];

        // If a display is selected we ignore the display group selection
        $filterCriteria['displayId'] = $displayId;
        if (empty($displayId) && count($displayGroupIds) > 0) {
            $filterCriteria['displayGroupId'] = $displayGroupIds;
        }

        $filterCriteria['type'] = $type;
        if ($type == 'layout') {
            $filterCriteria['layoutId'] = $selectedId;
        } elseif ($type == 'media') {
            $filterCriteria['mediaId'] = $selectedId;
        } elseif ($type == 'event') {
            $filterCriteria['eventTag'] = $eventTag;
        }

        $filterCriteria['filter'] = $filter;

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
        $type = $sanitizedParams->getString('type');
        $filter = $sanitizedParams->getString('filter');

        $saveAs = null;
        if ($type == 'layout') {
            try {
                $layout = $this->layoutFactory->getById($sanitizedParams->getInt('layoutId'));
            } catch (NotFoundException $error) {
                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($sanitizedParams->getInt('layoutId'));
                $layoutId = $this->layoutFactory->getLatestLayoutIdFromLayoutHistory($campaignId);
                $layout = $this->layoutFactory->getById($layoutId);
            }
            $saveAs = sprintf(__('%s report for Layout %s'), ucfirst($filter), $layout->layout);
        } elseif ($type == 'media') {
            try {
                $media = $this->mediaFactory->getById($sanitizedParams->getInt('mediaId'));
                $saveAs = sprintf(__('%s report for Media'), ucfirst($filter), $media->name);
            } catch (NotFoundException $error) {
                $saveAs = __('Media not found');
            }
        } elseif ($type == 'event') {
            $saveAs = sprintf(__('%s report for Event %s'), ucfirst($filter), $sanitizedParams->getString('eventTag'));
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        $metadata = [
            'periodStart' => $json['metadata']['periodStart'],
            'periodEnd' => $json['metadata']['periodEnd'],
            'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)
                ->format(DateFormatHelper::getSystemFormat()),
            'title' => $savedReport->saveAs,
        ];

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
        $type = strtolower($sanitizedParams->getString('type'));
        $layoutId = $sanitizedParams->getInt('layoutId');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $eventTag = $sanitizedParams->getString('eventTag');

        // Filter by displayId?
        $displayIds = $this->getDisplayIdFilter($sanitizedParams);

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

        switch ($reportFilter) {
            case 'today':
                $fromDt = $now->copy()->startOfDay();
                $toDt = $fromDt->copy()->addDay();
                $groupByFilter = 'byhour';
                break;

            case 'yesterday':
                $fromDt = $now->copy()->startOfDay()->subDay();
                $toDt = $now->copy()->startOfDay();
                $groupByFilter = 'byhour';
                break;

            case 'thisweek':
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek();
                $toDt = $fromDt->copy()->addWeek();
                $groupByFilter = 'byday';
                break;

            case 'thismonth':
                $fromDt = $now->copy()->startOfMonth();
                $toDt = $fromDt->copy()->addMonth();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case 'thisyear':
                $fromDt = $now->copy()->startOfYear();
                $toDt = $fromDt->copy()->addYear();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case 'lastweek':
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek()->subWeek();
                $toDt = $fromDt->copy()->addWeek();
                $groupByFilter = 'byday';
                break;

            case 'lastmonth':
                $fromDt = $now->copy()->startOfMonth()->subMonth();
                $toDt = $fromDt->copy()->addMonth();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case 'lastyear':
                $fromDt = $now->copy()->startOfYear()->subYear();
                $toDt = $fromDt->copy()->addYear();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case '':
            default:
                // Expect dates to be provided.
                $fromDt = $sanitizedParams->getDate('statsFromDt', ['default' => Carbon::now()->subDay()]);
                $fromDt->startOfDay();

                $toDt = $sanitizedParams->getDate('statsToDt', ['default' =>  Carbon::now()]);
                $toDt->addDay()->startOfDay();

                // What if the fromdt and todt are exactly the same?
                // in this case assume an entire day from midnight on the fromdt to midnight on the todt
                // (i.e. add a day to the todt)
                if ($fromDt == $toDt) {
                    $toDt->addDay();
                }

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');

                break;
        }

        // Get Results!
        // -------------
        // Validate we have necessary selections
        if (($type === 'media' && empty($mediaId))
            || ($type === 'layout' && empty($layoutId))
            || ($type === 'event' && empty($eventTag))
        ) {
            // We have nothing to return because the filter selections don't make sense.
            $result = [];
        } elseif ($this->getTimeSeriesStore()->getEngine() === 'mongodb') {
            $result = $this->getSummaryReportMongoDb(
                $fromDt,
                $toDt,
                $groupByFilter,
                $displayIds,
                $type,
                $layoutId,
                $mediaId,
                $eventTag,
                $reportFilter
            );
        } else {
            $result = $this->getSummaryReportMySql(
                $fromDt,
                $toDt,
                $groupByFilter,
                $displayIds,
                $type,
                $layoutId,
                $mediaId,
                $eventTag
            );
        }

        //
        // Output Results
        // --------------
        // TODO: chart definition in the backend - surely this should be frontend logic?!
        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        // Sanitize results for chart and table
        $rows = [];
        if (count($result) > 0) {
            foreach ($result['result'] as $row) {
                $sanitizedRow = $this->sanitizer->getSanitizer($row);

                // ----
                // Build Chart data
                $labels[] = $row['label'];

                $backgroundColor[] = 'rgb(95, 186, 218, 0.6)';
                $borderColor[] = 'rgb(240,93,41, 0.8)';

                $count = $sanitizedRow->getInt('NumberPlays');
                $countData[] = ($count == '') ? 0 : $count;

                $duration = $sanitizedRow->getInt('Duration');
                $durationData[] = ($duration == '') ? 0 : $duration;

                // ----
                // Build Tabular data
                $entry = [];
                $entry['label'] = $sanitizedRow->getString('label');
                $entry['duration'] = ($duration == '') ? 0 : $duration;
                $entry['count'] = ($count == '') ? 0 : $count;
                $rows[] = $entry;
            }
        }

        // Build Chart to pass in twig file chart.js
        $chart = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Total duration'),
                        'yAxisID' => 'Duration',
                        'backgroundColor' => $backgroundColor,
                        'data' => $durationData
                    ],
                    [
                        'label' => __('Total count'),
                        'yAxisID' => 'Count',
                        'borderColor' => $borderColor,
                        'type' => 'line',
                        'fill' => false,
                        'data' =>  $countData
                    ]
                ]
            ],
            'options' => [
                'scales' => [
                    'yAxes' => [
                        [
                            'id' => 'Duration',
                            'type' => 'linear',
                            'position' =>  'left',
                            'display' =>  true,
                            'scaleLabel' =>  [
                                'display' =>  true,
                                'labelString' => __('Duration(s)')
                            ],
                            'ticks' =>  [
                                'beginAtZero' => true
                            ]
                        ], [
                            'id' => 'Count',
                            'type' => 'linear',
                            'position' =>  'right',
                            'display' =>  true,
                            'scaleLabel' =>  [
                                'display' =>  true,
                                'labelString' => __('Count')
                            ],
                            'ticks' =>  [
                                'beginAtZero' => true
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $metadata =   [
            'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat()),
        ];

        // Total records
        $recordsTotal = count($rows);

        // ----
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            $metadata,
            $rows,
            $recordsTotal,
            $chart
        );
    }

    /**
     * MySQL summary report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, byday, byweek, bymonth
     * @param $displayIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @return array
     */
    private function getSummaryReportMySql(
        $fromDt,
        $toDt,
        $groupByFilter,
        $displayIds,
        $type,
        $layoutId,
        $mediaId,
        $eventTag
    ) {
        // Create periods covering the from/to dates
        // -----------------------------------------
        try {
            $periods = $this->getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [];
        }

        // Join in stats
        // -------------
        $select = '                      
        SELECT 
            start, 
            end, 
            periodsWithStats.id, 
            MAX(periodsWithStats.label) AS label,
            SUM(numberOfPlays) as NumberPlays, 
            CONVERT(SUM(periodsWithStats.actualDiff), SIGNED INTEGER) as Duration
         FROM (
            SELECT
                periods.id,
                periods.label,
                periods.start,
                periods.end,
                stat.count AS numberOfPlays,
                LEAST(stat.duration, LEAST(periods.end, statEnd, :toDt) - GREATEST(periods.start, statStart, :fromDt)) AS actualDiff
             FROM `' . $periods . '` AS periods
                LEFT OUTER JOIN (
                    SELECT 
                        layout.Layout,
                        IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
                        stat.mediaId,
                        stat.`start` as statStart,
                        stat.`end` as statEnd,
                        stat.duration,
                        stat.`count`
                      FROM stat
                        LEFT OUTER JOIN layout
                        ON layout.layoutID = stat.layoutID
                        LEFT OUTER JOIN `widget`
                        ON `widget`.widgetId = stat.widgetId
                        LEFT OUTER JOIN `widgetoption`
                        ON `widgetoption`.widgetId = `widget`.widgetId
                            AND `widgetoption`.type = \'attrib\'
                            AND `widgetoption`.option = \'name\'
                        LEFT OUTER JOIN `media`
                        ON `media`.mediaId = `stat`.mediaId
                     WHERE stat.type <> \'displaydown\' 
                        AND stat.start < :toDt
                        AND stat.end >= :fromDt
        ';

        $params = [
            'fromDt' => $fromDt->format('U'),
            'toDt' => $toDt->format('U')
        ];

        // Displays
        if (count($displayIds) > 0) {
            $select .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
        }

        // Type filter
        if ($type == 'layout' && $layoutId != '') {
            // Filter by Layout
            $select .= ' 
                AND `stat`.type = \'layout\' 
                AND `stat`.campaignId = (SELECT campaignId FROM layouthistory WHERE layoutId = :layoutId) 
            ';
            $params['layoutId'] = $layoutId;
        } elseif ($type == 'media' && $mediaId != '') {
            // Filter by Media
            $select .= '
                AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 
                AND `stat`.mediaId = :mediaId ';
            $params['mediaId'] = $mediaId;
        } elseif ($type == 'event' && $eventTag != '') {
            // Filter by Event
            $select .= '
                AND `stat`.type = \'event\'  
                AND `stat`.tag = :tag ';
            $params['tag'] = $eventTag;
        }

        $select .= ' 
                    ) stat               
                    ON statStart < periods.`end`
                        AND statEnd > periods.`start`
        ';

        // Periods and Stats tables are joined, we should only have periods we're interested in, but it
        // won't hurt to restrict them
        $select .= ' 
         WHERE periods.`start` < :toDt
            AND periods.`end` > :fromDt ';

        // Close out our containing view and group things together
        $select .= '
            ) periodsWithStats 
        GROUP BY periodsWithStats.id, start, end
        ORDER BY periodsWithStats.start
        ';

        return [
            'result' => $this->getStore()->select($select, $params),
            'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat())
        ];
    }

    /**
     * MongoDB summary report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, byday, byweek, bymonth
     * @param $displayIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @param $reportFilter
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getSummaryReportMongoDb(
        $fromDt,
        $toDt,
        $groupByFilter,
        $displayIds,
        $type,
        $layoutId,
        $mediaId,
        $eventTag,
        $reportFilter
    ) {

        $diffInDays = $toDt->diffInDays($fromDt);
        if ($groupByFilter == 'byhour') {
            $hour = 1;
            $input = range(0, 23);
        } elseif ($groupByFilter == 'byday') {
            $hour = 24;
            $input = range(0, $diffInDays - 1);
        } elseif ($groupByFilter == 'byweek') {
            $hour = 24 * 7;
            $input = range(0, ceil($diffInDays / 7));
        } elseif ($groupByFilter == 'bymonth') {
            $hour = 24;
            $input = range(0, ceil($diffInDays / 30));
        } else {
            $this->getLog()->error('Unknown Grouping Selected ' . $groupByFilter);
            throw new InvalidArgumentException(__('Unknown Grouping ') . $groupByFilter, 'groupByFilter');
        }

        $filterRangeStart = new UTCDateTime($fromDt->format('U') * 1000);
        $filterRangeEnd = new UTCDateTime($toDt->format('U') * 1000);

        // Extend the range
        if (($groupByFilter == 'byhour') || ($groupByFilter == 'byday')) {
            $extendedPeriodStart = $filterRangeStart;
            $extendedPeriodEnd = $filterRangeEnd;
        } elseif ($groupByFilter == 'byweek') {
            // Extend upto the start of the first week of the fromdt, and end of the week of the todt
            $startOfWeek = $fromDt->copy()->locale(Translate::GetLocale())->startOfWeek();
            $endOfWeek = $toDt->copy()->locale(Translate::GetLocale())->endOfWeek()->addSecond();
            $extendedPeriodStart = new UTCDateTime($startOfWeek->format('U') * 1000);
            $extendedPeriodEnd = new UTCDateTime($endOfWeek->format('U') * 1000);
        } elseif ($groupByFilter == 'bymonth') {
            if ($reportFilter == '') {
                // We extend the fromDt and toDt range filter
                // so that we can generate each month period
                $fromDtStartOfMonth = $fromDt->copy()->startOfMonth();
                $toDtEndOfMonth = $toDt->copy()->endOfMonth()->addSecond();

                // Generate all months that lie in the extended range
                $monthperiods = [];
                foreach ($input as $key => $value) {
                    $monthPeriodStart = $fromDtStartOfMonth->copy()->addMonth($key);
                    $monthPeriodEnd = $fromDtStartOfMonth->copy()->addMonth($key)->addMonth();

                    // Remove the month period which crossed the extended end range
                    if ($monthPeriodStart >= $toDtEndOfMonth) {
                        continue;
                    }
                    $monthperiods[$key]['start'] =  new UTCDateTime($monthPeriodStart->format('U') * 1000);
                    $monthperiods[$key]['end'] =    new UTCDateTime($monthPeriodEnd->format('U') * 1000);
                }

                $extendedPeriodStart = new UTCDateTime($fromDtStartOfMonth->format('U') * 1000);
                $extendedPeriodEnd = new UTCDateTime($toDtEndOfMonth->format('U') * 1000);
            } elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

                $start = $fromDt->copy()->subMonth()->startOfMonth();
                $end = $fromDt->copy()->startOfMonth();

                // Generate all 12 months
                $monthperiods = [];
                foreach ($input as $key => $value) {
                    $monthperiods[$key]['start'] = new UTCDateTime($start->addMonth()->format('U') * 1000);
                    $monthperiods[$key]['end'] = new UTCDateTime($end->addMonth()->format('U') * 1000);
                }
            }
        }

        $this->getLog()->debug('Period start: '
            . $filterRangeStart->toDateTime()->format(DateFormatHelper::getSystemFormat())
            . ' Period end: '. $filterRangeEnd->toDateTime()->format(DateFormatHelper::getSystemFormat()));

        // Type filter
        if (($type == 'layout') && ($layoutId != '')) {
            // Get the campaign ID
            $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);

            $matchType = [
                '$eq' => [ '$type', 'layout' ]
            ];
            $matchId = [
                '$eq' => [ '$campaignId', $campaignId ]
            ];
        } elseif (($type == 'media') && ($mediaId != '')) {
            $matchType = [
                '$eq' => [ '$type', 'media' ]
            ];
            $matchId = [
                '$eq' => [ '$mediaId', $mediaId ]
            ];
        } elseif (($type == 'event') && ($eventTag != '')) {
            $matchType = [
                '$eq' => [ '$type', 'event' ]
            ];
            $matchId = [
                '$eq' => [ '$eventName', $eventTag ]
            ];
        } else {
            throw new InvalidArgumentException(__('No match for event type'), 'type');
        }

        if ($groupByFilter == 'byweek') {
            // PERIOD GENERATION
            // Addition of 7 days from start
            $projectMap = [
                '$project' => [
                    'periods' =>  [
                        '$map' => [
                            'input' => $input,
                            'as' => 'number',
                            'in' => [
                                'start' => [
                                    '$add' => [
                                        $extendedPeriodStart,
                                        [
                                            '$multiply' => [
                                                '$$number',
                                                $hour * 3600000
                                            ]
                                        ]
                                    ]
                                ],
                                'end' => [
                                    '$add' => [
                                        [
                                            '$add' => [
                                                $extendedPeriodStart,
                                                $hour * 3600000
                                            ]
                                        ],
                                        [
                                            '$multiply' => [
                                                '$$number',
                                                $hour * 3600000
                                            ]
                                        ]
                                    ]
                                ],
                            ]
                        ]
                    ]
                ]
            ];
        } elseif ($groupByFilter == 'bymonth') {
            $projectMap = [
                '$project' => [
                    'periods' => [
                        '$map' => [
                            'input' => $monthperiods,
                            'as' => 'number',
                            'in' => [
                                'start' => '$$number.start',
                                'end' => '$$number.end',
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            // PERIOD GENERATION
            // Addition of 1 day/hour from start
            $projectMap = [
                '$project' => [
                    'periods' =>  [
                        '$map' => [
                            'input' => $input,
                            'as' => 'number',
                            'in' => [
                                'start' => [
                                    '$add' => [
                                        $extendedPeriodStart,
                                        [
                                            '$multiply' => [
                                                $hour * 3600000,
                                                '$$number'
                                            ]
                                        ]
                                    ]
                                ],
                                'end' => [
                                    '$add' => [
                                        [
                                            '$add' => [
                                                $extendedPeriodStart,
                                                [
                                                    '$multiply' => [
                                                        $hour * 3600000,
                                                        '$$number'
                                                    ]
                                                ]
                                            ]
                                        ]
                                        , $hour * 3600000
                                    ]
                                ],
                            ]
                        ]
                    ]
                ]
            ];
        }

        // GROUP BY
        $groupBy = [
            'period_start' => '$period_start',
            'period_end' => '$period_end'
        ];

        // PERIODS QUERY
        $cursorPeriodQuery = [

            $projectMap,

            // periods needs to be unwind to merge next
            [
                '$unwind' => '$periods'
            ],

            // replace the root to eliminate _id and get only periods
            [
                '$replaceRoot' => [
                    'newRoot' => '$periods'
                ]
            ],

            [
                '$project' => [
                    'start' => 1,
                    'end' => 1,
                ]
            ],

            [
                '$match' => [
                    'start' => [
                        '$lt' => $extendedPeriodEnd
                    ],
                    'end' => [
                        '$gt' => $extendedPeriodStart
                    ]
                ]
            ],

        ];

        // Periods result
        $periods = $this->getTimeSeriesStore()->executeQuery([
            'collection' => $this->periodTable,
            'query' => $cursorPeriodQuery
        ]);

        $matchExpr = [

            // match media id / layout id
            $matchType,
            $matchId,
        ];

        // display ids
        if (count($displayIds) > 0) {
            $matchExpr[] = [
                '$in' => [ '$displayId', $displayIds ]
            ];
        }

        // stat.start < period end AND stat.end > period start
        // for example, when report filter 'today' is selected
        // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
        // and end is greater than or equal first hour of the day
        $matchExpr[] = [
            '$lt' => [ '$start', '$statdata.periods.end' ]
        ];
        $matchExpr[] = [
            '$gt' => [ '$end', '$statdata.periods.start' ]
        ];


        // STAT AGGREGATION QUERY
        $statQuery = [

            [
                '$match' => [
                    'start' =>  [
                        '$lt' => $filterRangeEnd
                    ],
                    'end' => [
                        '$gt' => $filterRangeStart
                    ],
                ]
            ],

            [
                '$lookup' => [
                    'from' => 'period',
                    'let' => [
                        'stat_start' => '$start',
                        'stat_end' => '$end',
                        'stat_duration' => '$duration',
                        'stat_count' => '$count',
                    ],
                    'pipeline' => [
                        $projectMap,
                        [
                            '$unwind' => '$periods'
                        ],

                    ],
                    'as' => 'statdata'
                ]
            ],

            [
                '$unwind' => '$statdata'
            ],

            [
                '$match' => [
                    '$expr' => [
                        '$and' => $matchExpr
                    ]
                ]
            ],

            [
                '$project' => [
                    '_id' => 1,
                    'count' => 1,
                    'duration' => 1,
                    'start' => 1,
                    'end' => 1,
                    'period_start' => '$statdata.periods.start',
                    'period_end' => '$statdata.periods.end',
                    'monthNo' => [
                        '$month' =>  '$statdata.periods.start'
                    ],
                    'yearDate' => [
                        '$isoWeekYear' =>  '$statdata.periods.start'
                    ],
                    'weekNo' => [
                        '$isoWeek' =>  '$statdata.periods.start'
                    ],
                    'actualStart' => [
                        '$max' => [ '$start', '$statdata.periods.start', $filterRangeStart ]
                    ],
                    'actualEnd' => [
                        '$min' => [ '$end', '$statdata.periods.end', $filterRangeEnd ]
                    ],
                    'actualDiff' => [
                        '$min' => [
                            '$duration',
                            [
                                '$divide' => [
                                    [
                                        '$subtract' => [
                                            ['$min' => [ '$end', '$statdata.periods.end', $filterRangeEnd ]],
                                            ['$max' => [ '$start', '$statdata.periods.start', $filterRangeStart ]]
                                        ]
                                    ], 1000
                                ]
                            ]
                        ]
                    ],

                ]

            ],

            [
                '$group' => [
                    '_id' => $groupBy,
                    'period_start' => ['$first' => '$period_start'],
                    'period_end' => ['$first' => '$period_end'],
                    'NumberPlays' => ['$sum' => '$count'],
                    'Duration' => ['$sum' => '$actualDiff'],
                    'start' => ['$first' => '$start'],
                    'end' => ['$first' => '$end'],
                ]
            ],

            [
                '$project' => [
                    'start' => '$start',
                    'end' => '$end',
                    'period_start' => 1,
                    'period_end' => 1,
                    'NumberPlays' => ['$toInt' => '$NumberPlays'],
                    'Duration' => ['$toInt' => '$Duration'],
                ]
            ],

        ];

        // Stats result
        $results = $this->getTimeSeriesStore()->executeQuery([
            'collection' => $this->table,
            'query' => $statQuery
        ]);

        // Run period loop and map the stat result for each period
        $resultArray = [];

        foreach ($periods as $key => $period) {
            // UTC date format
            $period_start_u = $period['start']->toDateTime()->format('U');
            $period_end_u = $period['end']->toDateTime()->format('U');

            // CMS date
            $period_start = Carbon::createFromTimestamp($period_start_u);
            $period_end = Carbon::createFromTimestamp($period_end_u);

            if ($groupByFilter == 'byhour') {
                $label = $period_start->format('g:i A');
            } elseif ($groupByFilter == 'byday') {
                if (($reportFilter == 'thisweek') || ($reportFilter == 'lastweek')) {
                    $label = $period_start->format('D');
                } else {
                    $label = $period_start->format('Y-m-d');
                }
            } elseif ($groupByFilter == 'byweek') {
                $weekstart = $period_start->format('M d');
                $weekend = $period_end->format('M d');
                $weekno = $period_start->locale(Translate::GetLocale())->week();

                if ($period_start_u < $fromDt->copy()->format('U')) {
                    $weekstart = $fromDt->copy()->format('M-d');
                }

                if ($period_end_u > $toDt->copy()->format('U')) {
                    $weekend = $toDt->copy()->format('M-d');
                }
                $label = $weekstart . ' - ' . $weekend . ' (w' . $weekno . ')';
            } elseif ($groupByFilter == 'bymonth') {
                $label = $period_start->format('M');
                if ($reportFilter == '') {
                    $label .= ' ' .$period_start->format('Y');
                }
            } else {
                $label = 'N/A';
            }

            $matched = false;
            foreach ($results as $k => $result) {
                if ($result['period_start'] == $period['start']) {
                    $NumberPlays = $result['NumberPlays'];
                    $Duration = $result['Duration'];
                    $matched = true;
                    break;
                }
            }

            // Chart label
            $resultArray[$key]['label'] = $label;
            if ($matched == true) {
                $resultArray[$key]['NumberPlays'] = $NumberPlays;
                $resultArray[$key]['Duration'] = $Duration;
            } else {
                $resultArray[$key]['NumberPlays'] = 0;
                $resultArray[$key]['Duration'] = 0;
            }
        }

        return [
            'result' => $resultArray,
            'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat())
        ];
    }
}
