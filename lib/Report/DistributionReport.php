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
use Xibo\Factory\DisplayGroupFactory;
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
 * Class DistributionReport
 * @package Xibo\Report
 */
class DistributionReport implements ReportInterface
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
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

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

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->savedReportFactory = $container->get('savedReportFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
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
        return 'distribution-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'distribution-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'distribution-report-form',
            'distributionReport',
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

        $data = [];
        $data['formTitle'] = $formParams['title'];
        $data['hiddenFields'] = json_encode([
            'type' => $type,
            'selectedId' => $formParams['selectedId'],
            'eventTag' => $eventTag ?? null
        ]);
        $data['reportName'] = 'distributionReport';

        return [
            'template' => 'distribution-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $groupByFilter = $sanitizedParams->getString('groupByFilter');
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
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupByFilter'] = $groupByFilter;
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

        if ($type == 'layout') {
            try {
                $layout = $this->layoutFactory->getById($sanitizedParams->getInt('layoutId'));
            } catch (NotFoundException $error) {
                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($sanitizedParams->getInt('layoutId'));
                $layoutId = $this->layoutFactory->getLatestLayoutIdFromLayoutHistory($campaignId);
                $layout = $this->layoutFactory->getById($layoutId);
            }

            $saveAs = sprintf(__('%s report for Layout %s', ucfirst($filter), $layout->layout));
        } elseif ($type == 'media') {
            try {
                $media = $this->mediaFactory->getById($sanitizedParams->getInt('mediaId'));
                $saveAs = sprintf(__('%s report for Media %s', ucfirst($filter), $media->name));
            } catch (NotFoundException $error) {
                $saveAs = __('Media not found');
            }
        } elseif ($type == 'event') {
            $saveAs = sprintf(__('%s report for Event %s', ucfirst($filter), $sanitizedParams->getString('eventTag')));
        }

        // todo: ???
        if (!empty($filterCriteria['displayId'])) {
            // Get display
            try {
                $displayName = $this->displayFactory->getById($filterCriteria['displayId'])->display;
                $saveAs .= ' ('. __('Display') . ': '. $displayName . ')';
            } catch (NotFoundException $error) {
                $saveAs .= ' '.__('(DisplayId: Not Found)');
            }
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        $metadata = [ 'periodStart' => $json['metadata']['periodStart'],
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

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $type = strtolower($sanitizedParams->getString('type'));
        $layoutId = $sanitizedParams->getInt('layoutId');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $eventTag = $sanitizedParams->getString('eventTag');

        // Filter by displayId?
        $displayIds = $this->getDisplayIdFilter($sanitizedParams);

        // Get an array of display groups this user has access to
        $displayGroupIds = [];

        foreach ($this->displayGroupFactory->query(null, [
            'isDisplaySpecific' => -1,
            'userCheckUserId' => $this->getUser()->userId
        ]) as $displayGroup) {
            $displayGroupIds[] = $displayGroup->displayGroupId;
        }

        if (count($displayGroupIds) <= 0) {
            throw new InvalidArgumentException(__('No display groups with View permissions'), 'displayGroup');
        }

        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether the user has to enter their own from / to dates
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

                $toDt = $sanitizedParams->getDate('statsToDt', ['default' =>  Carbon::now()]);
                $toDt->addDay()->startOfDay();

                // What if the fromdt and todt are exactly the same?
                // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
                if ($fromDt == $toDt) {
                    $toDt->addDay();
                }

                break;
        }

        // Use the group by filter provided
        // NB: this differs from the Summary Report where we set the group by according to the range selected
        $groupByFilter = $sanitizedParams->getString('groupByFilter');

        //
        // Get Results!
        // -------------
        $timeSeriesStore = $this->getTimeSeriesStore()->getEngine();
        if ($timeSeriesStore == 'mongodb') {
            $result = $this->getDistributionReportMongoDb(
                $fromDt,
                $toDt,
                $groupByFilter,
                $displayIds,
                $displayGroupIds,
                $type,
                $layoutId,
                $mediaId,
                $eventTag
            );
        } else {
            $result = $this->getDistributionReportMySql(
                $fromDt,
                $toDt,
                $groupByFilter,
                $displayIds,
                $displayGroupIds,
                $type,
                $layoutId,
                $mediaId,
                $eventTag
            );
        }

        //
        // Output Results
        // --------------
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
                // Chart labels in xaxis
                $labels[] = $row['label'];

                $backgroundColor[] = 'rgb(95, 186, 218, 0.6)';
                $borderColor[] = 'rgb(240,93,41, 0.8)';

                $count = ($row['NumberPlays'] == '') ? 0 : $row['NumberPlays'];
                $countData[] = $count;

                $duration = ($row['Duration'] == '') ? 0 : $row['Duration'];
                $durationData[] = $duration;

                // ----
                // Build Tabular data
                $entry = [];
                $entry['label'] = $sanitizedRow->getString('label');
                $entry['duration'] = $duration;
                $entry['count'] = $count;
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
     * MySQL distribution report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, bydayofweek and bydayofmonth
     * @param $displayIds
     * @param $displayGroupIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @return array
     */
    private function getDistributionReportMySql(
        $fromDt,
        $toDt,
        $groupByFilter,
        $displayIds,
        $displayGroupIds,
        $type,
        $layoutId,
        $mediaId,
        $eventTag
    ) {
        // Only return something if we have the necessary options selected.
        if (($type == 'media' && $mediaId != '')
            || ($type == 'layout' && $layoutId != '')
            || ($type == 'event' && $eventTag != '')
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
            SELECT periodsWithStats.id,
                MIN(periodsWithStats.start) AS start,
                MAX(periodsWithStats.end) AS end, 
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
                    LEAST(stat.duration, LEAST(periods.end, statEnd, :toDt) 
                                             - GREATEST(periods.start, statStart, :fromDt)) AS actualDiff
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
             WHERE periods.`start` >= :fromDt
                AND periods.`end` <= :toDt ';

            // Close out our containing view and group things together
            $select .= '
                ) periodsWithStats 
            GROUP BY periodsWithStats.id, periodsWithStats.label
            ORDER BY periodsWithStats.id
            ';

            return [
                'result' => $this->getStore()->select($select, $params),
                'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat())
            ];
        } else {
            return [];
        }
    }

    private function getDistributionReportMongoDb($fromDt, $toDt, $groupByFilter, $displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag)
    {
        if ((($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != ''))) {
            // Get the timezone
            $timezone = Carbon::parse()->getTimezone()->getName();
            $filterRangeStart = new UTCDateTime($fromDt->format('U') * 1000);
            $filterRangeEnd = new UTCDateTime($toDt->format('U') * 1000);

            $diffInDays = $toDt->diffInDays($fromDt);
            if ($groupByFilter == 'byhour') {
                $hour = 1;
                $input = range(0, 24 * $diffInDays - 1); // subtract 1 as we start from 0
                $id = '$hour';
            } elseif ($groupByFilter == 'bydayofweek') {
                $hour = 24;
                $input = range(0, $diffInDays - 1);
                $id = '$isoDayOfWeek';
            } elseif ($groupByFilter == 'bydayofmonth') {
                $hour = 24;
                $input = range(0, $diffInDays - 1);
                $id = '$dayOfMonth';
            } else {
                $this->getLog()->error('Unknown Grouping Selected ' . $groupByFilter);
                throw new InvalidArgumentException(__('Unknown Grouping ') . $groupByFilter, 'groupByFilter');
            }

            // Dateparts for period generation
            $dateFromParts['month'] = $fromDt->month;
            $dateFromParts['year'] = $fromDt->year;
            $dateFromParts['day'] = $fromDt->day;
            $dateFromParts['hour'] = 0;

            // PERIODS QUERY
            $cursorPeriodQuery = [

                [
                    '$addFields' => [

                        'period_start' => [
                            '$dateFromParts' =>  [
                                'year' => $dateFromParts['year'],
                                'month' => $dateFromParts['month'],
                                'day' =>  $dateFromParts['day'],
                                'hour' =>  $dateFromParts['hour'],
                                'timezone' =>  $timezone,
                            ]
                        ]
                    ]
                ],

                [
                    '$project' => [

                        'periods' => [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$add' => [
                                            '$period_start',
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
                                                    '$period_start',
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
                ],

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
                        'id' => [
                            $id => [
                                'date' => '$start',
                                'timezone'=> $timezone
                            ]
                        ],
                    ]
                ],

                [
                    '$group' => [
                        '_id' => [
                            'id' => '$id'
                        ],
                        'start' => ['$first' => '$start'],
                        'end' => ['$first' => '$end'],
                        'id' => ['$first' => '$id'],
                    ]
                ],

                [
                    '$match' => [
                        'start' => [
                            '$lt' => $filterRangeEnd
                        ],
                        'end' => [
                            '$gt' => $filterRangeStart
                        ]
                    ]
                ],

                [
                    '$sort' => ['id' => 1]
                ]

            ];

            // Periods result
            $periods = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->periodTable, 'query' => $cursorPeriodQuery]);

            // We extend the stat start and stat end so that we can push required periods for them
            if (($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth')) {
                $datePartStart = [
                    '$dateFromParts' =>  [
                        'year' => [
                            '$year' => '$start'
                        ],
                        'month' => [
                            '$month' => '$start'
                        ],
                        'day' => [
                            '$dayOfMonth' => '$start'
                        ],
                    ]
                ];

                $datePartEnd = [
                    '$dateFromParts' =>  [
                        'year' => [
                            '$year' => '$end'
                        ],
                        'month' => [
                            '$month' => '$end'
                        ],
                        'day' => [
                            '$dayOfMonth' => '$end'
                        ],
                    ]
                ];
            } else { // by hour
                $datePartStart = [
                    '$dateFromParts' =>  [
                        'year' => [
                            '$year' => '$start'
                        ],
                        'month' => [
                            '$month' => '$start'
                        ],
                        'day' => [
                            '$dayOfMonth' => '$start'
                        ],
                        'hour' => [
                            '$hour' => '$start'
                        ],
                    ]
                ];

                $datePartEnd = [
                    '$dateFromParts' =>  [
                        'year' => [
                            '$year' => '$end'
                        ],
                        'month' => [
                            '$month' => '$end'
                        ],
                        'day' => [
                            '$dayOfMonth' => '$end'
                        ],
                        'hour' => [
                            '$hour' => '$end'
                        ],
                    ]
                ];
            }

            $match = [
                '$match' => [
                    'start' => [
                        '$lt' => $filterRangeEnd
                    ],
                    'end' => [
                        '$gt' => $filterRangeStart
                    ],
                    'type' => $type,
                ]
            ];

            if (count($displayIds) > 0) {
                $match['$match']['displayId'] = [
                    '$in' => $displayIds
                ];
            }

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {
                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);
                $match['$match']['campaignId'] = $campaignId;
            } elseif (($type == 'media') && ($mediaId != '')) {
                $match['$match']['mediaId'] = $mediaId;
            } elseif (($type == 'event') && ($eventTag != '')) {
                $match['$match']['eventName'] = $eventTag;
            }

            // STAT AGGREGATION QUERY
            $statQuery = [

                $match,
                [
                    '$project' => [
                        'count' => 1,
                        'duration' => 1,
                        'start' => [
                            '$dateFromParts' =>  [
                                'year' => [
                                    '$year' => [
                                        'date' => '$start',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'month' => [
                                    '$month' => [
                                        'date' => '$start',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'day' => [
                                    '$dayOfMonth' => [
                                        'date' => '$start',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'hour' => [
                                    '$hour' => [
                                        'date' => '$start',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'minute' => [
                                    '$minute' => [
                                        'date' => '$start',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'second' => [
                                    '$second' => [
                                        'date' => '$start',
                                        'timezone' => $timezone,

                                    ]
                                ],
                            ]
                        ],
                        'end' => [
                            '$dateFromParts' =>  [
                                'year' => [
                                    '$year' => [
                                        'date' => '$end',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'month' => [
                                    '$month' => [
                                        'date' => '$end',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'day' => [
                                    '$dayOfMonth' => [
                                        'date' => '$end',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'hour' => [
                                    '$hour' => [
                                        'date' => '$end',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'minute' => [
                                    '$minute' => [
                                        'date' => '$end',
                                        'timezone' => $timezone,

                                    ]
                                ],
                                'second' => [
                                    '$second' => [
                                        'date' => '$end',
                                        'timezone' => $timezone,

                                    ]
                                ],
                            ]
                        ]

                    ]
                ],


                [
                    '$addFields' => [
                        'period_start_backward' => $datePartStart,
                        'period_end_forward' => [
                            '$add' => [
                                $datePartEnd,
                                $hour * 3600000
                            ]
                        ]
                    ]
                ],

                [
                    '$project' => [
                        'start' => 1,
                        'end' => 1,
                        'count' => 1,
                        'duration' => 1,
                        'period_start_backward' => 1,
                        'period_end_forward' => 1,
                        'range' => [
                            '$range' => [
                                0,
                                [
                                    '$ceil' => [
                                        '$divide' => [
                                            [
                                                '$subtract' => [
                                                    '$period_end_forward',
                                                    '$period_start_backward'
                                                ]
                                            ],
                                            $hour * 3600000
                                        ]
                                    ]
                                ]
                            ]
                        ],

                        'period_start' => [
                            '$dateFromParts' =>  [
                                'year' => [
                                    '$year' => '$period_start_backward'
                                ],
                                'month' => [
                                    '$month' => '$period_start_backward'
                                ],
                                'day' => [
                                    '$dayOfMonth' => '$period_start_backward'
                                ],
                                'hour' => [
                                    '$hour' => '$period_start_backward'
                                ],
                            ]
                        ]
                    ]
                ],

                [
                    '$project' => [
                        'start' => 1,
                        'end' => 1,
                        'count' => 1,
                        'duration' => 1,
                        'periods' => [
                            '$map' => [
                                'input' => '$range',
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$add' => [
                                            '$period_start',
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
                                                    '$period_start',
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
                ],

                [
                    '$unwind' => '$periods'
                ],
                [
                    '$match' => [
                        'periods.start' => ['$lt' => $filterRangeEnd ],
                        'periods.end' => ['$gt' => $filterRangeStart ],
                    ]
                ],
                [
                    '$project' => [
                        'start' => 1,
                        'end' => 1,
                        'count' => 1,
                        'duration' => 1,
                        'period_start' => '$periods.start',
                        'period_end' => '$periods.end',
                        'id' => [
                            $id => [
                                'date' => '$periods.start',
                                'timezone'=> 'UTC'
                            ]
                        ],

                        'actualStart' => [
                            '$max' => ['$start', '$periods.start', $filterRangeStart]
                        ],
                        'actualEnd' => [
                            '$min' => ['$end', '$periods.end', $filterRangeEnd]
                        ],
                        'actualDiff' => [
                            '$min' => [
                                '$duration',
                                [
                                    '$divide' => [
                                        [
                                            '$subtract' => [
                                                ['$min' => ['$end', '$periods.end', $filterRangeEnd]],
                                                ['$max' => ['$start', '$periods.start', $filterRangeStart]]
                                            ]
                                        ], 1000
                                    ]
                                ]
                            ]
                        ],

                    ]

                ],
                [
                    '$match' => [
                        '$expr' => [
                            '$lt' => ['$actualStart' , '$actualEnd' ],
                        ]

                    ]
                ],

                [
                    '$group' => [
                        '_id' => [
                            'id' => '$id'
                        ],
                        'period_start' => ['$first' => '$period_start'],
                        'period_end' => ['$first' => '$period_end'],
                        'NumberPlays' => ['$sum' => '$count'],
                        'Duration' => ['$sum' => '$actualDiff'],
                        'start' => ['$first' => '$start'],
                        'end' => ['$first' => '$end'],
                        'id' => ['$first' => '$id'],

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
                        'id' => 1,


                    ]
                ],

                [
                    '$sort' => ['id' => 1]
                ]

            ];

            // Stats result
            $results = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->table, 'query' => $statQuery]);

            // Run period loop and map the stat result for each period
            $resultArray = [];
            $day = [ 1 => 'Mon', 2 => 'Tues', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

            foreach ($periods as $key => $period) {
                $id = $period['id'];

                if ($groupByFilter == 'byhour') {
                    $label = Carbon::createFromTimestamp($period['start']->toDateTime()->format('U'))->format('g:i A');
                } elseif ($groupByFilter == 'bydayofweek') {
                    $label = $day[$id];
                } elseif ($groupByFilter == 'bydayofmonth') {
                    $label = Carbon::createFromTimestamp($period['start']->toDateTime()->format('U'))->format('d');
                }

                $matched = false;
                foreach ($results as $k => $result) {
                    if ($result['id'] == $period['id']) {
                        $NumberPlays = $result['NumberPlays'];
                        $Duration = $result['Duration'];

                        $matched = true;
                        break;
                    }
                }
                
                $resultArray[$key]['id'] = $id;
                $resultArray[$key]['label'] = $label;

                if ($matched == true) {
                    $resultArray[$key]['NumberPlays'] = $NumberPlays;
                    $resultArray[$key]['Duration'] = $Duration;
                } else {
                    $resultArray[$key]['NumberPlays'] = 0;
                    $resultArray[$key]['Duration'] = 0;
                }
            }

            $this->getLog()->debug('Period start: ' . $fromDt->format(DateFormatHelper::getSystemFormat()) . ' Period end: ' . $toDt->format(DateFormatHelper::getSystemFormat()));

            return [
                'result' => $resultArray,
                'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat())
            ];
        } else {
            return [];
        }
    }
}
