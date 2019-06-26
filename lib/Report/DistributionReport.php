<?php

namespace Xibo\Report;

use Jenssegers\Date\Date;
use MongoDB\BSON\UTCDateTime;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
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

        return $this;
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'distribution-report-form',
            'data' =>  [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];
    }

    /** @inheritdoc */
    public function getReportScheduleFormData()
    {
        $type = $this->getSanitizer()->getParam('type', 'layout');

        if ($type == 'event') {
            $selectedId = 0; // we only need tag
            $tag = $this->getSanitizer()->getParam('eventTag', null);
            $title = __('Add distribution report Schedule for '). $type. ' - '. $tag;
        }

        $data = [];

        $data['formTitle'] = $title;

        $data['hiddenFields'] =  json_encode([
            'type' => $type,
            'selectedId' => (int) $selectedId,
            'tag' => isset($tag) ? $tag : null
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
        $tag = $hiddenFields['tag'];

        $filterCriteria['displayId'] = $displayId;
        $filterCriteria['type'] = $type;
        if ($type == 'event') {
            $filterCriteria['eventTag'] = $tag;
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

        // Return
        return [
            'filterCriteria' => json_encode($filterCriteria),
            'schedule' => $schedule
        ];
    }

    /** @inheritdoc */
    public function generateSavedReportName($filterCriteria)
    {
        if ($filterCriteria['type'] == 'event') {
            $saveAs = 'Distribution report: '. ucfirst($filterCriteria['filter']). ' report for Event '. $filterCriteria['eventTag'];
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

        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1)));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse()));

        $eventTag = $this->getSanitizer()->getString('eventTag', $filterCriteria);
        $reportFilter = $this->getSanitizer()->getString('reportFilter', $filterCriteria);
        $groupByFilter = $this->getSanitizer()->getString('groupByFilter', $filterCriteria);

        $displayId = $this->getSanitizer()->getInt('displayId', $filterCriteria);
        $displayGroupId = $this->getSanitizer()->getInt('displayGroupId', $filterCriteria);

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        $diffInDays = $toDt->diffInDays($fromDt);

        // Format param dates
        $fromDt = $this->getDate()->getLocalDate($fromDt);
        $toDt = $this->getDate()->getLocalDate($toDt);

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

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get an array of display groups this user has access to
        $displayGroupIds = [];

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            $displayGroupIds[] = $displayGroup->displayGroupId;
        }

        if (count($displayGroupIds) <= 0)
            throw new InvalidArgumentException(__('No display groups with View permissions'), 'displayGroup');

        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        // Get store
        $timeSeriesStore = $this->getTimeSeriesStore()->getEngine();
        $this->getLog()->debug('Timeseries store is ' . $timeSeriesStore);

        if ($timeSeriesStore == 'mongodb') {
            $result = $this->getDistributionReportMongoDb($displayIds, $displayGroupIds, $diffInDays, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
        } else {
            $result = $this->getDistributionReportMySql($displayIds, $displayGroupIds, $diffInDays, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
        }

        // Summary report result in chart
        if (count($result) > 0) {
            foreach ($result['result'] as $row) {
                // Day Label
                $dayLabel = [1 => 'Mon', 2 => 'Tues', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

                // Label
                $tsLabel = $this->getDate()->parse($row['start'], 'U');

                if ($reportFilter == '') {

                    if ($groupByFilter == 'byhour') {
                        $tsLabel = $tsLabel->format('g:i A ');

                    } elseif ($groupByFilter == 'bydayofweek') {
                        $tsLabel = $dayLabel[$row['groupbycol']];

                    } elseif ($groupByFilter == 'bydayofmonth') {
                        $tsLabel = $row['groupbycol'];
                    }

                } elseif (($reportFilter == 'today') || ($reportFilter == 'yesterday')) {
                    if ($groupByFilter == 'byhour') {
                        $tsLabel = $tsLabel->format('g:i A');

                    } elseif ($groupByFilter == 'bydayofweek') {
                        $tsLabel = $dayLabel[$row['groupbycol']];

                    } elseif ($groupByFilter == 'bydayofmonth') {
                        $tsLabel = $row['groupbycol'];
                    }

                } elseif (($reportFilter == 'lastweek') || ($reportFilter == 'thisweek')) {

                    if ($groupByFilter == 'byhour') {
                        $tsLabel = $tsLabel->format('g:i A');

                    } elseif ($groupByFilter == 'bydayofweek') {
                        $tsLabel = $dayLabel[$row['groupbycol']];

                    } elseif ($groupByFilter == 'bydayofmonth') {
                        $tsLabel = $row['groupbycol'];
                    }

                } elseif (($reportFilter == 'thismonth') || ($reportFilter == 'lastmonth')) {

                    if ($groupByFilter == 'byhour') {
                        $tsLabel = $tsLabel->format('g:i A ');

                    } elseif ($groupByFilter == 'bydayofweek') {
                        $tsLabel = $dayLabel[$row['groupbycol']];

                    } elseif ($groupByFilter == 'bydayofmonth') {
                        $tsLabel = $row['groupbycol'];
                    }

                } elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {

                    if ($groupByFilter == 'byhour') {
                        $tsLabel = $tsLabel->format('g:i A ');

                    } elseif ($groupByFilter == 'bydayofweek') {
                        $tsLabel = $dayLabel[$row['groupbycol']];

                    } elseif ($groupByFilter == 'bydayofmonth') {
                        $tsLabel = $row['groupbycol'];
                    }
                }

                // Chart labels in xaxis
                $labels[] = $tsLabel;

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
            'periodStart' => isset($result['periodStart']) ? $result['periodStart'] : '',
            'periodEnd' => isset($result['periodEnd']) ? $result['periodEnd'] : '',
            'labels' => $labels,
            'countData' => $countData,
            'durationData' => $durationData,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
        ];

    }

    function getDistributionReportMySql($displayIds, $displayGroupIds, $diffInDays, $eventTag, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        // range - by hour, by dayofweek, by dayofmonth
        // today and yesterday - only by hour
        // thisweek and lastweek - only by hour
        // thismonth and lastmonth - by hour, by dayofweek, by dayofmonth
        // thisyear and lastyear - by dayofweek, by dayofmonth

        if ($eventTag != '') {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('U');
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('U');

            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('U');
            $today = $this->dateService->parse()->startOfDay()->format('U');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('U');

            if ($reportFilter == '') {
                $filterRangeStart = $fromDt;
                $filterRangeEnd = $toDt;

                if ($groupByFilter == 'byhour') {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, ($diffInDays + 1) * 24);
                    
                    $start = $this->dateService->parse($fromDt, 'U')->startOfDay()->subHour();
                    $end = $this->dateService->parse($fromDt, 'U')->startOfDay();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    // Maximum number of period records in the periods view
                    $ranges = range(0, $diffInDays);
                    
                    $start = $this->dateService->parse($fromDt, 'U')->startOfDay()->subDay();
                    $end = $this->dateService->parse($fromDt, 'U')->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);
                }

            } elseif (($reportFilter == 'today')) {
                $filterRangeStart = $today;
                $filterRangeEnd = $nextday;

                if ($groupByFilter == 'byhour') {

                    $ranges = range(1, 24);

                    $start = $this->dateService->parse()->startOfDay()->subHour();
                    $end = $this->dateService->parse()->startOfDay();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);


                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 1);

                    $start = $this->dateService->parse()->subDay()->startOfDay();
                    $end = $this->dateService->parse()->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);
                }

            } elseif (($reportFilter == 'yesterday')) {
                $filterRangeStart = $yesterday;
                $filterRangeEnd = $today;

                if ($groupByFilter == 'byhour') {

                    $ranges = range(1, 24);

                    $start = $this->dateService->parse()->subDay()->startOfDay()->subHour();
                    $end = $this->dateService->parse()->subDay()->startOfDay();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    $ranges = range(1, 1);

                    $start = $this->dateService->parse()->subDay()->subDay()->startOfDay();
                    $end = $this->dateService->parse()->subDay()->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);

                }

            } elseif (($reportFilter == 'thisweek')) {
                $filterRangeStart = $this->dateService->parse()->startOfWeek()->format('U');  //firstdaythisweek
                $filterRangeEnd = $this->dateService->parse()->endOfWeek()->addSecond()->format('U');//lastdaythisweek

                if ($groupByFilter == 'byhour') {

                    $ranges = range(1, 24 * 7);

                    $start = $this->dateService->parse()->startOfWeek()->subHour();
                    $end = $this->dateService->parse()->startOfWeek();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);


                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    $ranges = range(1, 7);

                    $start = $this->dateService->parse()->startOfWeek()->subDay();
                    $end = $this->dateService->parse()->startOfWeek();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);

                }

            } elseif (($reportFilter == 'lastweek')) {
                $filterRangeStart = $this->dateService->parse()->subWeek()->startOfWeek()->format('U'); //firstdaylastweek
                $filterRangeEnd = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('U'); //lastdaylastweek

                if ($groupByFilter == 'byhour') {

                    $ranges = range(1, 24 * 7);

                    $start = $this->dateService->parse()->subWeek()->startOfWeek()->subHour();
                    $end = $this->dateService->parse()->subWeek()->startOfWeek();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    $ranges = range(1, 24 * 7);

                    $start = $this->dateService->parse()->subWeek()->startOfWeek()->subDay();
                    $end = $this->dateService->parse()->subWeek()->startOfWeek();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);

                }

            } elseif (($reportFilter == 'thismonth')) {
                $filterRangeStart = $this->dateService->parse()->startOfMonth()->format('U'); //firstdaythismonth
                $filterRangeEnd = $this->dateService->parse()->endOfMonth()->addSecond()->format('U'); //lastdaythismonth

                if ($groupByFilter == 'byhour') {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 24 * 31);
                    
                    $start = $this->dateService->parse()->startOfMonth()->subHour();
                    $end = $this->dateService->parse()->startOfMonth();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 31);
                    
                    $start = $this->dateService->parse()->startOfMonth()->subDay()->startOfDay();
                    $end = $this->dateService->parse()->startOfMonth()->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);
                }

            } elseif (($reportFilter == 'lastmonth')) {
                $filterRangeStart = $this->dateService->parse()->startOfMonth()->subMonth()->format('U'); //firstdaylastmonth
                $filterRangeEnd = $this->dateService->parse()->startOfMonth()->subMonth()->endOfMonth()->addSecond()->format('U'); //lastdaylastmonth

                if ($groupByFilter == 'byhour') {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 24 * 31);
                    
                    $start = $this->dateService->parse()->subMonth()->startOfMonth()->subHour();
                    $end = $this->dateService->parse()->subMonth()->startOfMonth();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 31);
                    
                    $start = $this->dateService->parse()->subMonth()->startOfMonth()->subDay()->startOfDay();
                    $end = $this->dateService->parse()->subMonth()->startOfMonth()->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);
                }

            } elseif (($reportFilter == 'thisyear')) {
                $filterRangeStart = $this->dateService->parse()->startOfYear()->format('U'); //firstdaythisyear
                $filterRangeEnd = $this->dateService->parse()->endOfYear()->addSecond()->format('U'); //lastdaythisyear

                if ($groupByFilter == 'byhour') {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 24 * 366);

                    $start = $this->dateService->parse()->startOfYear()->subHour();
                    $end = $this->dateService->parse()->startOfYear();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 366);
                    
                    $start = $this->dateService->parse()->startOfYear()->subDay()->startOfDay();
                    $end = $this->dateService->parse()->startOfYear()->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);
                }

            } elseif (($reportFilter == 'lastyear')) {
                $filterRangeStart = $this->dateService->parse()->startOfYear()->subYear()->format('U'); //firstdaylastyear
                $filterRangeEnd = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('U'); //lastdaylastyear

                if ($groupByFilter == 'byhour') {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 24 * 366);

                    $start = $this->dateService->parse()->subYear()->startOfYear()->subHour();
                    $end = $this->dateService->parse()->subYear()->startOfYear();

                    // Get all hours of the period
                    $periodData = $this->generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges);

                } elseif ( ($groupByFilter == 'bydayofweek') || ($groupByFilter == 'bydayofmonth') ) {

                    // Maximum number of period records in the periods view
                    $ranges = range(1, 366);
                    
                    $start = $this->dateService->parse()->subYear()->startOfYear()->subDay()->startOfDay();
                    $end = $this->dateService->parse()->subYear()->startOfYear()->startOfDay();

                    // Get all days of the period
                    $periodData = $this->generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter);
                }
            }

            $select = '                      
                SELECT start, end, groupbycol,
                SUM(count) as NumberPlays, 
                CONVERT(SUM(B.actualDiff), SIGNED INTEGER) as Duration
            FROM (
                SELECT
                     *,
                    GREATEST(periods.start, statStart, '. $filterRangeStart . ') AS actualStart,
                    LEAST(periods.end, statEnd, '. $filterRangeEnd . ') AS actualEnd,
                    LEAST(stat.duration, LEAST(periods.end, statEnd, '. $filterRangeEnd . ') - GREATEST(periods.start, statStart, '. $filterRangeStart . ')) AS actualDiff
                FROM
                ( 
                    SELECT                
                        
            ';

            // Period views
            $string = ' ';
            $inc =   0;
            foreach ($periodData as $key => $p) {

                $string .= $p['start'].'  start, '.$p['end']. ' end, '.$p['groupbycol']. ' groupbycol ';

                $inc++;
                if ($inc != count($periodData)) {
                    $string .= ' UNION ALL SELECT ';
                }
            }

            $periods = '            
                                ' .$string.'
                ) periods        
            ';

            // BODY
            $body = '
                LEFT OUTER JOIN
                
                (SELECT 
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
            ';

            // Displays
            if (count($displayIds) > 0) {
                $body .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
            }

            $body .= ' AND `stat`.type = \'event\'  
                       AND `stat`.tag = "' . $eventTag. '"';

            $params = [
                'fromDt' => $fromDt,
                'toDt' => $toDt
            ];

            // where start is less than last day/hour of the period
            // and end is greater than or equal first day/hour of the period
            $body .= ' AND stat.`start` <  '.$filterRangeEnd.'
            AND stat.`end` >= '.$filterRangeStart.' ';

            $body .= ' ) stat               
            ON statStart < periods.`end`
            AND statEnd > periods.`start`
            ';

            // e.g.,
            // where periods start is greater than or equal today and
            // periods end is less than or equal today + 1 day i.e. nextday
            $body .= ' WHERE periods.`start` >= '.$filterRangeStart.'
            AND periods.`end` <= '.$filterRangeEnd.' ';

            // ORDER BY
            $body .= '  
            ORDER BY periods.`start`, statStart
	        ) B ';

            // GROUP BY
            if(($reportFilter == '')) {

                $body .= '
                GROUP BY groupbycol 
                ORDER BY start ';

            } else {

                $body .= '
                GROUP BY groupbycol 
                ORDER BY groupbycol ';

            }

            /*Execute sql statement*/
            $query = $select . $periods . $body;
            $this->getLog()->debug($query);

            $options['query'] = $query;
            $options['params'] = $params;

            $results = $this->getTimeSeriesStore()->executeQuery($options);

            return [
                'result' => $results,
                'periodStart' => $this->dateService->parse($filterRangeStart, 'U')->format('Y-m-d H:i:s'),
                'periodEnd' => $this->dateService->parse($filterRangeEnd, 'U')->format('Y-m-d H:i:s')
            ];

        } else {
            return [];
        }
    }

    function getDistributionReportMongoDb($displayIds, $displayGroupIds, $diffInDays, $eventTag, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {

        // range - by hour, by dayofweek, by dayofmonth
        // today and yesterday - only by hour
        // thisweek and lastweek - only by hour
        // thismonth and lastmonth - by hour, by dayofweek, by dayofmonth
        // thisyear and lastyear - by dayofweek, by dayofmonth

        if ($eventTag != '') {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('U');
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('U');

            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('U');
            $today = $this->dateService->parse()->startOfDay()->format('U');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('U');

            if ($reportFilter == '') {

                $filterRangeStart = new UTCDateTime($fromDt * 1000);
                $filterRangeEnd = new UTCDateTime($toDt * 1000);

                if ($groupByFilter == 'byhour') {

                    $hour = 1;
                    $input = range(0, ( ($diffInDays + 1) * 24 -1) );

                } elseif ($groupByFilter == 'bydayofweek') {

                    $hour = 24;
                    $input = range(0, $diffInDays);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, $diffInDays);
                }

            }

            elseif (($reportFilter == 'today')) {

                $filterRangeStart = new UTCDateTime($today * 1000);
                $filterRangeEnd = new UTCDateTime($nextday * 1000);

                if ($groupByFilter == 'byhour') {

                    $hour = 1;
                    $input = range(0, 23);

                } elseif ($groupByFilter == 'bydayofweek') {

                    $hour = 24;
                    $input = range(0, 0);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 0);
                }

            }

            elseif (($reportFilter == 'yesterday')) {

                $filterRangeStart = new UTCDateTime($yesterday * 1000);
                $filterRangeEnd = new UTCDateTime($today * 1000);

                if ($groupByFilter == 'byhour') {

                    $hour = 1;
                    $input = range(0, 23);

                } elseif ($groupByFilter == 'bydayofweek') {

                    $hour = 24;
                    $input = range(0, 0);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 0);
                }
            }

            elseif (($reportFilter == 'thisweek')) {

                $startUTC = $this->dateService->parse()->startOfWeek()->format('U');
                $endUTC = $this->dateService->parse()->endOfWeek()->addSecond()->format('U');

                $filterRangeStart = new UTCDateTime( $startUTC * 1000);
                $filterRangeEnd = new UTCDateTime( $endUTC * 1000);

                if ($groupByFilter == 'byhour') {

                     $hour = 1;
                     $input = range(0, 24 * 7 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {

                    $hour = 24;
                    $input = range(0, 6);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 6);
                }
            }

            elseif (($reportFilter == 'lastweek')) {

                $startUTC = $this->dateService->parse()->startOfWeek()->subWeek()->format('U'); //firstdaylastweek
                $endUTC = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('U'); //lastdaylastweek

                $filterRangeStart = new UTCDateTime( $startUTC * 1000);
                $filterRangeEnd = new UTCDateTime( $endUTC * 1000);

                if ($groupByFilter == 'byhour') {

                    $hour = 1;
                    $input = range(0, 24 * 7 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {

                    $hour = 24;
                    $input = range(0, 6);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 6);
                }

            } elseif (($reportFilter == 'thisyear')) {

                $startUTC = $this->dateService->parse()->startOfYear()->format('U'); //firstdaythisyear
                $endUTC = $this->dateService->parse()->endOfYear()->addSecond()->format('U'); //lastdaythisyear

                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);

                if ($groupByFilter == 'byhour') {
                    $hour = 1;
                    $input = range(0, 24 * 365 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {
                    $hour = 24;
                    $input = range(0, 365);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 365);
                }

            }

            elseif (($reportFilter == 'lastyear')) {

                $startUTC = $this->dateService->parse()->startOfYear()->subYear()->format('U'); //firstdaylastyear
                $endUTC = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('U'); //lastdaylastyear

                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);

                if ($groupByFilter == 'byhour') {
                    $hour = 1;
                    $input = range(0, 24 * 365 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {
                    $hour = 24;
                    $input = range(0, 365);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 365);
                }
            }

            $this->getLog()->debug('Period start: '.$filterRangeStart->toDateTime()->format('Y-m-d H:i:s'). ' Period end: '. $filterRangeEnd->toDateTime()->format('Y-m-d H:i:s'));

             // Match query
            $matchType = [
                '$eq' => [ '$type', 'event' ]
            ];
            $matchId = [
                '$eq' => [ '$eventName', $eventTag ]
            ];

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
                                        $filterRangeStart,
                                        [
                                            '$multiply' => [
                                                $hour*3600000,
                                                '$$number'
                                            ]
                                        ]
                                    ]
                                ],
                                'end' => [
                                    '$add' => [
                                        [
                                            '$add' => [
                                                $filterRangeStart,
                                                [
                                                    '$multiply' => [
                                                        $hour*3600000,
                                                        '$$number'
                                                    ]
                                                ]
                                            ]
                                        ]
                                        , $hour*3600000
                                    ]
                                ],
                                'groupbycol' => '$$number'
                            ]
                        ]
                    ]
                ]
            ];

            // GROUP BY
            if ($groupByFilter == 'bydayofweek'){

                $groupBy = [
                    'dayofweek' => '$dayofweek'
                ];

                $sort = [
                    '$sort' => ['dayofweek'=> 1]
                ];

            } elseif ($groupByFilter == 'bydayofmonth'){

                $groupBy = [
                    'dayofmonth' => '$dayofmonth'
                ];

                $sort = [
                    '$sort' => ['dayofmonth'=> 1]
                ];

            } else { // ($groupByFilter == 'byhour')

                $groupBy = [
                    'hour' => '$hour'
                ];

                $sort = [
                    '$sort' => ['hour'=> 1]
                ];
            }

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
                        'groupbycol' => 1,
                        'hour' => [
                            '$hour' =>  '$start'
                        ],
                        'dayofweek' => [
                            '$dayOfWeek' =>  '$start'
                        ],
                        'dayofmonth' => [
                            '$dayOfMonth' =>  '$start'
                        ],
                    ]
                ],

                [
                    '$group' => [
                        '_id' => $groupBy,
                        'start' => ['$first' => '$start'],
                        'end' => ['$first' => '$end'],
                        'groupbycol' => ['$first' => '$groupbycol'],
                        'hour' => ['$first' => '$hour'],
                        'dayofweek' => ['$first' => '$dayofweek'],
                        'dayofmonth' => ['$first' => '$dayofmonth'],
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

                 $sort

            ];

            // Periods result
            $periods = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->periodTable, 'query' => $cursorPeriodQuery]);

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
                            '$and' => [

                                // match media id / layout id
                                $matchType,
                                $matchId,

                                // display ids
                                [
                                    '$in' => [ '$displayId', $displayIds ]
                                ],

                                // stat.start < period end AND stat.end > period start
                                // for example, when report filter 'today' is selected
                                // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
                                // and end is greater than or equal first hour of the day
                                [
                                    '$lt' => [ '$start', '$statdata.periods.end' ]
                                ],
                                [
                                    '$gt' => [ '$end', '$statdata.periods.start' ]
                                ],
                            ]
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
                        'groupbycol' => '$statdata.periods.groupbycol',
                        'hour' => [
                            '$hour' =>  '$statdata.periods.start'
                        ],
                        'dayofweek' => [
                            '$dayOfWeek' =>  '$statdata.periods.start'
                        ],
                        'dayofmonth' => [
                            '$dayOfMonth' =>  '$statdata.periods.start'
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
                        'groupbycol' => ['$first' => '$groupbycol'],
                        'hour' => ['$first' => '$hour'],
                        'dayofweek' => ['$first' => '$dayofweek'],
                        'dayofmonth' => ['$first' => '$dayofmonth'],
                    ]
                ],

                [
                    '$project' => [
                        'start' => '$start',
                        'end' => '$end',
                        'groupbycol' => '$groupbycol',
                        'hour' => '$hour',
                        'dayofweek' => '$dayofweek',
                        'dayofmonth' => '$dayofmonth',
                        'period_start' => 1,
                        'period_end' => 1,
                        'NumberPlays' => 1,
                        'Duration' => 1,
                    ]
                ],

            ];

            // Stats result
            $results = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->table, 'query' => $statQuery]);

            // Run period loop and map the stat result for each period
            $resultArray = [];
            foreach ($periods as $key => $period) {

                $period_start = $period['start']->toDateTime()->format('U');
                $period_end = $period['end']->toDateTime()->format('U');

                if ($groupByFilter == 'byhour') {
                    $groupbycol = $period['hour'];

                } elseif ($groupByFilter == 'bydayofweek') {
                    $groupbycol = $period['dayofweek'];

                } elseif ($groupByFilter == 'bydayofmonth') {
                    $groupbycol = $period['dayofmonth'];
                }

                $matched = false;
                foreach ($results as $k => $result) {

                   if ( ($reportFilter == 'today') || ($reportFilter == 'yesterday') ) {
                       if( $result['period_start'] == $period['start'] ) {

                           $NumberPlays = $result['NumberPlays'];
                           $Duration = $result['Duration'];

                           $matched = true;
                           break;
                       }
                   } else {
                      if ($groupByFilter == 'byhour') {
                          if( $result['hour'] == $period['hour'] ) {

                              $NumberPlays = $result['NumberPlays'];
                              $Duration = $result['Duration'];

                              $matched = true;
                              break;
                          }
                      }
                      elseif ($groupByFilter == 'bydayofweek') {
                          if( $result['dayofweek'] == $period['dayofweek'] ) {

                              $NumberPlays = $result['NumberPlays'];
                              $Duration = $result['Duration'];

                              $matched = true;
                              break;
                          }
                      }
                      elseif ($groupByFilter == 'bydayofmonth') {
                          if( $result['dayofmonth'] == $period['dayofmonth'] ) {

                              $NumberPlays = $result['NumberPlays'];
                              $Duration = $result['Duration'];

                              $matched = true;
                              break;
                          }
                      }
                   }
                }
                
                $resultArray[$key]['groupbycol'] = $groupbycol;
                $resultArray[$key]['start'] = $period_start;
                $resultArray[$key]['end'] = $period_end;

                if($matched == true) {
                    $resultArray[$key]['NumberPlays'] = $NumberPlays;
                    $resultArray[$key]['Duration'] = $Duration;

                } else {
                    $resultArray[$key]['NumberPlays'] = 0;
                    $resultArray[$key]['Duration'] = 0;

                }
            }

            return [
                'result' => $resultArray,
                'periodStart' => $this->dateService->parse($filterRangeStart->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s'),
                'periodEnd' => $this->dateService->parse($filterRangeEnd->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s')
            ];

        } else {
            return [];
        }
    }
}