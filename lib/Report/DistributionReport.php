<?php

namespace Xibo\Report;

use MongoDB\BSON\UTCDateTime;
use Xibo\Entity\ReportSchedule;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class DistributionReport
 * @package Xibo\Report
 */
class DistributionReport implements ReportInterface
{

    use ReportTrait;

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
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    private $table = 'stat';

    private $periodTable = 'period';

    /**
     * Report Constructor.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     */
    public function __construct($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer)
    {
        $this->setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer);
    }

    /** @inheritdoc */
    public function setFactories($container)
    {

        $this->displayFactory = $container->get('displayFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->savedReportFactory = $container->get('savedReportFactory');
        $this->userFactory = $container->get('userFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->reportService = $container->get('reportService');

        return $this;
    }

    /** @inheritdoc */
    public function getReportChartScript($results)
    {
        $labels = str_replace('"', "'", $results['chartData']['labels']);
        $countData = str_replace('"', "'", $results['chartData']['countData']);
        $durationData = str_replace('"', "'", $results['chartData']['durationData']);

        return "{type:'bar',data:{labels:".$labels.", datasets:[{label:'Total duration',yAxisID:'Duration',data:".$durationData."},{ label: 'Total count', yAxisID:'Count',borderColor: 'rgb(240,93,41, 0.8)', data: ".$countData.", type:'line', fill: 'false'}]}, 
        options: {
            scales: {
                yAxes: [{
                    id: 'Duration',
                    type: 'linear',
                    position: 'left',
                    display: true,
                    scaleLabel: {
                        display: true,
                        labelString: 'Duration(s)'
                    },
                    ticks: {
                        beginAtZero:true
                    }
                }, {
                    id: 'Count',
                    type: 'linear',
                    position: 'right',
                    display: true,
                    scaleLabel: {
                        display: true,
                        labelString: 'Count'
                    },
                    ticks: {
                        beginAtZero:true
                    }
                }]
            },
            maintainAspectRatio: true,
        }}";
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'distribution-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'distribution-report-form',
            'data' =>  [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate(),
                'availableReports' => $this->reportService->listReports()
            ]
        ];
    }

    /** @inheritdoc */
    public function getReportScheduleFormData()
    {
        $type = $this->getSanitizer()->getParam('type', '');

        if ($type == 'layout') {
            $selectedId = $this->getSanitizer()->getParam('layoutId', null);
            $title = __('Add Report Schedule for '). $type. ' - '.
                $this->layoutFactory->getById($selectedId)->layout;

        } else if ($type == 'media') {
            $selectedId = $this->getSanitizer()->getParam('mediaId', null);
            $title = __('Add Report Schedule for '). $type. ' - '.
                $this->mediaFactory->getById($selectedId)->name;

        } else if ($type == 'event') {
            $selectedId = 0; // we only need eventTag
            $eventTag = $this->getSanitizer()->getParam('eventTag', null);
            $title = __('Add Report Schedule for '). $type. ' - '. $eventTag;

        }

        $data = [];

        $data['formTitle'] = $title;

        $data['hiddenFields'] =  json_encode([
            'type' => $type,
            'selectedId' => (int) $selectedId,
            'eventTag' => isset($eventTag) ? $eventTag : null
        ]);

        $data['reportName'] = 'distributionReport';

        return [
            'template' => 'distribution-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData()
    {
        $filter = $this->getSanitizer()->getString('filter');
        $groupByFilter = $this->getSanitizer()->getString('groupByFilter');
        $displayId = $this->getSanitizer()->getString('displayId');
        $hiddenFields = json_decode($this->getSanitizer()->getParam('hiddenFields', null), true);

        $type = $hiddenFields['type'];
        $selectedId = $hiddenFields['selectedId'];
        $eventTag = $hiddenFields['eventTag'];

        $filterCriteria['displayId'] = $displayId;
        $filterCriteria['type'] = $type;
        if ($type == 'layout') {
            $filterCriteria['layoutId'] = $selectedId;
        } else if ($type == 'media') {
            $filterCriteria['mediaId'] = $selectedId;
        } else if ($type == 'event') {
            $filterCriteria['eventTag'] = $eventTag;
        }

        $filterCriteria['filter'] = $filter;

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';
            $filterCriteria['groupByFilter'] = $groupByFilter;

        } else if ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
            $filterCriteria['groupByFilter'] = $groupByFilter;

        } else if ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupByFilter'] = $groupByFilter;

        } else if ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        }

        $filterCriteria['sendEmail'] = $this->getSanitizer()->getCheckbox('sendEmail');
        $filterCriteria['nonusers'] = $this->getSanitizer()->getString('nonusers');

        // Return
        return [
            'filterCriteria' => json_encode($filterCriteria),
            'schedule' => $schedule
        ];
    }

    /** @inheritdoc */
    public function generateSavedReportName($filterCriteria)
    {
        if ($filterCriteria['type'] == 'layout') {
            try {
                $layout = $this->layoutFactory->getById($filterCriteria['layoutId']);

            } catch (NotFoundException $error) {

                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($filterCriteria['layoutId']);
                $layoutId = $this->layoutFactory->getLatestLayoutIdFromLayoutHistory($campaignId);
                $layout = $this->layoutFactory->getById($layoutId);

            }

            $saveAs = __(ucfirst($filterCriteria['filter']). ' report for') . ' Layout '. $layout->layout;


        } else if ($filterCriteria['type'] == 'media') {
            try {
                $media = $this->mediaFactory->getById($filterCriteria['mediaId']);
                $saveAs = __(ucfirst($filterCriteria['filter']). ' report for') . ' Media '. $media->name;

            } catch (NotFoundException $error) {
                $saveAs = 'Media ' . __('Not Found');
            }

        } else if ($filterCriteria['type'] == 'event') {
            $saveAs = __(ucfirst($filterCriteria['filter']). ' report for') . ' Event '. $filterCriteria['eventTag'];

        } else {
            $saveAs = __(ucfirst($filterCriteria['filter']). ' report for') . ' Type '. $filterCriteria['type'];
        }

        if (!empty($filterCriteria['displayId'])) {

            // Get display
            try{
                $displayName = $this->displayFactory->getById($filterCriteria['displayId'])->display;
                $saveAs .= ' (Display: '. $displayName . ')';

            } catch (NotFoundException $error){
                $saveAs .= ' (DisplayId: ' . __('Not Found') . ' )';
            }
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Return data to build chart
        return [
            'template' => 'distribution-report-preview',
            'chartData' => [
                'savedReport' => $savedReport,
                'generatedOn' => $this->dateService->parse($savedReport->generatedOn, 'U')->format('Y-m-d H:i:s'),
                'periodStart' => isset($json['periodStart']) ? $json['periodStart'] : '',
                'periodEnd' => isset($json['periodEnd']) ? $json['periodEnd'] : '',
                'labels' => json_encode($json['labels']),
                'countData' => json_encode($json['countData']),
                'durationData' => json_encode($json['durationData']),
                'backgroundColor' => json_encode($json['backgroundColor']),
                'borderColor' => json_encode($json['borderColor']),
            ]
        ];
    }

    /** @inheritdoc */
    public function getResults($filterCriteria)
    {
        $this->getLog()->debug('Filter criteria: '. json_encode($filterCriteria, JSON_PRETTY_PRINT));

        $type = strtolower($this->getSanitizer()->getString('type', $filterCriteria));
        $layoutId = $this->getSanitizer()->getInt('layoutId', $filterCriteria);
        $mediaId = $this->getSanitizer()->getInt('mediaId', $filterCriteria);
        $eventTag = $this->getSanitizer()->getString('eventTag', $filterCriteria);

        $displayId = $this->getSanitizer()->getInt('displayId', $filterCriteria);
        $displayGroupId = $this->getSanitizer()->getInt('displayGroupId', $filterCriteria);

        // Get an array of display id this user has access to.
        $displayIds = [];

        // Get an array of display id this user has access to.
        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        // Set displayIds as [-1] if the user selected a display for which they don't have permission
        if ($displayId != 0) {
            if (!in_array($displayId, $displayIds)) {
                $displayIds = [-1];
            } else {
                $displayIds = [$displayId];
            }
        }

        if (count($displayIds) <= 0) {
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
        }

        // Get an array of display groups this user has access to
        $displayGroupIds = [];

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            $displayGroupIds[] = $displayGroup->displayGroupId;
        }

        if (count($displayGroupIds) <= 0) {
            throw new InvalidArgumentException(__('No display groups with View permissions'), 'displayGroup');
        }

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $this->getSanitizer()->getString('reportFilter', $filterCriteria);

        // Use the current date as a helper
        $now = $this->getDate()->parse();

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
                $fromDt = $now->copy()->startOfWeek();
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
                $fromDt = $now->copy()->startOfWeek()->subWeek();
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
                $fromDt = $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1));
                $fromDt->startOfDay();

                $toDt = $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse());
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
        $groupByFilter = $this->getSanitizer()->getString('groupByFilter', $filterCriteria);

        //
        // Get Results!
        // -------------
        $timeSeriesStore = $this->getTimeSeriesStore()->getEngine();
        if ($timeSeriesStore == 'mongodb') {
            $result = $this->getDistributionReportMongoDb($fromDt, $toDt, $groupByFilter, $displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag);
        } else {
            $result = $this->getDistributionReportMySql($fromDt, $toDt, $groupByFilter, $displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag);
        }

        //
        // Output Results
        // --------------
        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        // Format the results for output on a chart
        if (count($result) > 0) {
            foreach ($result['result'] as $row) {
                // Chart labels in xaxis
                $labels[] = $row['label'];

                $backgroundColor[] = 'rgb(95, 186, 218, 0.6)';
                $borderColor[] = 'rgb(240,93,41, 0.8)';

                $count = $this->getSanitizer()->int($row['NumberPlays']);
                $countData[] = ($count == '') ? 0 : $count;

                $duration = $this->getSanitizer()->int($row['Duration']);
                $durationData[] = ($duration == '') ? 0 : $duration;
            }
        }

        // Return data to build chart
        return [
            'periodStart' => $this->getDate()->getLocalDate($fromDt),
            'periodEnd' => $this->getDate()->getLocalDate($toDt),
            'labels' => $labels,
            'countData' => $countData,
            'durationData' => $durationData,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
        ];

    }

    /**
     * MySQL distribution report
     * @param \Jenssegers\Date\Date $fromDt The filter range from date
     * @param \Jenssegers\Date\Date $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, bydayofweek and bydayofmonth
     * @param $displayIds
     * @param $displayGroupIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @return array
     */
    private function getDistributionReportMySql($fromDt, $toDt, $groupByFilter, $displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag)
    {
        // Only return something if we have the necessary options selected.
        if (
            (($type == 'media') && ($mediaId != ''))
            || (($type == 'layout') && ($layoutId != ''))
            || (($type == 'event') && ($eventTag != ''))
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
            SELECT start, end, periodsWithStats.id, periodsWithStats.label,
                SUM(count) as NumberPlays, 
                CONVERT(SUM(periodsWithStats.actualDiff), SIGNED INTEGER) as Duration
             FROM (
                SELECT
                     *,
                    GREATEST(periods.start, statStart, :fromDt) AS actualStart,
                    LEAST(periods.end, statEnd, :toDt) AS actualEnd,
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
            if (($type == 'layout') && ($layoutId != '')) {
                // Filter by Layout
                $select .= ' 
                    AND `stat`.type = \'layout\' 
                    AND `stat`.campaignId = (SELECT campaignId FROM layouthistory WHERE layoutId = :layoutId) 
                ';
                $params['layoutId'] = $layoutId;

            } elseif (($type == 'media') && ($mediaId != '')) {
                // Filter by Media
                $select .= '
                    AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 
                    AND `stat`.mediaId = :mediaId ';
                $params['mediaId'] = $mediaId;

            } elseif (($type == 'event') && ($eventTag != '')) {
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
            // wont hurt to restrict them
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
                'periodStart' => $fromDt->format('Y-m-d H:i:s'),
                'periodEnd' => $toDt->format('Y-m-d H:i:s')
            ];

        } else {
            return [];
        }
    }

    private function getDistributionReportMongoDb($fromDt, $toDt, $groupByFilter, $displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != '')) ) {

            // Get the timezone
            $timezone = $this->getDate()->parse()->getTimezone()->getName();
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
                throw new InvalidArgumentException('Unknown Grouping ' . $groupByFilter, 'groupByFilter');
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
            if ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') )

            {
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
                    'displayId' => [
                        '$in' => $displayIds
                    ]
                ]
            ];

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
                        'NumberPlays' => 1,
                        'Duration' => 1,
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

                if($groupByFilter == 'byhour'){
                    $label = $this->getDate()->parse($period['start']->toDateTime()->format('U'), 'U')->format('g:i A');
                } elseif ($groupByFilter == 'bydayofweek') {
                    $label = $day[$id];
                } elseif ($groupByFilter == 'bydayofmonth') {
                    $label = $this->getDate()->parse($period['start']->toDateTime()->format('U'), 'U')->format('d');
                }

                $matched = false;
                foreach ($results as $k => $result) {
                  if( $result['id'] == $period['id'] ) {

                      $NumberPlays = $result['NumberPlays'];
                      $Duration = $result['Duration'];

                      $matched = true;
                      break;
                  }
                }
                
                $resultArray[$key]['id'] = $id;
                $resultArray[$key]['label'] = $label;

                if($matched == true) {
                    $resultArray[$key]['NumberPlays'] = $NumberPlays;
                    $resultArray[$key]['Duration'] = $Duration;

                } else {
                    $resultArray[$key]['NumberPlays'] = 0;
                    $resultArray[$key]['Duration'] = 0;

                }
            }

            $this->getLog()->debug('Period start: ' . $fromDt->format('Y-m-d H:i:s') . ' Period end: ' . $toDt->format('Y-m-d H:i:s'));

            return [
                'result' => $resultArray,
                'periodStart' => $fromDt->format('Y-m-d H:i:s'),
                'periodEnd' => $toDt->format('Y-m-d H:i:s')
            ];

        } else {
            return [];
        }
    }
}