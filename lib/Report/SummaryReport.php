<?php

namespace Xibo\Report;

use MongoDB\BSON\UTCDateTime;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class SummaryReport
 * @package Xibo\Report
 */
class SummaryReport implements ReportInterface
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
        return $this;
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'summary-report-form',
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

        if ($type == 'layout') {
            $selectedId = $this->getSanitizer()->getParam('layoutId', null);
            $title = __('Add Report Schedule for '). $type. ' - '.
                    $this->layoutFactory->getById($selectedId)->layout;

        } else if ($type == 'media') {
            $selectedId = $this->getSanitizer()->getParam('mediaId', null);
            $title = __('Add Report Schedule for '). $type. ' - '.
                    $this->mediaFactory->getById($selectedId)->name;

        } else if ($type == 'event') {
            $selectedId = 0; // we only need tag
            $tag = $this->getSanitizer()->getParam('eventTag', null);
            $title = __('Add Report Schedule for '). $type. ' - '. $tag;

        }

        return [
            'title' => $title,
            'hiddenFields' => [
                'type' => $type,
                'selectedId' => (int) $selectedId,
                'tag' => isset($tag) ? $tag : null
            ]
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData()
    {
        $filter = $this->getSanitizer()->getString('filter');
        $hiddenFields = json_decode($this->getSanitizer()->getParam('hiddenFields', null), true);

        $type = $hiddenFields['type'];
        $selectedId = $hiddenFields['selectedId'];
        $tag = $hiddenFields['tag'];

        $filterCriteria['type'] = $type;
        if ($type == 'layout') {
            $filterCriteria['layoutId'] = $selectedId;
        } else if ($type == 'media') {
            $filterCriteria['mediaId'] = $selectedId;
        } else if ($type == 'event') {
            $filterCriteria['tag'] = $tag;
        }

        $filterCriteria['filter'] = $filter;

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';

        } else if ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
            $filterCriteria['groupFilter'] = 'byweek';

        } else if ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupFilter'] = 'bymonth';

        } else if ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupFilter'] = 'bymonth';
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

        if ($filterCriteria['type'] == 'layout') {
            $layout = $this->layoutFactory->getById($filterCriteria['layoutId']);
            $saveAs = ucfirst($filterCriteria['filter']). ' report for Layout '. $layout->layout;

        } else if ($filterCriteria['type'] == 'media') {
            $media = $this->mediaFactory->getById($filterCriteria['mediaId']);
            $saveAs = ucfirst($filterCriteria['filter']). ' report for Media '. $media->name;

        } else if ($filterCriteria['type'] == 'event') {
            $saveAs = ucfirst($filterCriteria['filter']). ' report for Event '. $filterCriteria['tag'];
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {

        // Return data to build chart
        return [
            'template' => 'summary-report-preview',
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

        $type = strtolower($this->getSanitizer()->getString('type', $filterCriteria));
        $layoutId = $this->getSanitizer()->getInt('layoutId', $filterCriteria);
        $mediaId = $this->getSanitizer()->getInt('mediaId', $filterCriteria);
        $eventTag = $this->getSanitizer()->getString('tag', $filterCriteria);
        $reportFilter = $this->getSanitizer()->getString('reportFilter', $filterCriteria);
        $groupByFilter = $this->getSanitizer()->getString('groupByFilter', $filterCriteria);

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

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        // Get store
        $timeSeriesStore = $this->getTimeSeriesStore()->getEngine();
        $this->getLog()->debug('Timeseries store is '. $timeSeriesStore);

        if ($timeSeriesStore == 'mongodb') {
            $result =  $this->getSummaryReportMongoDb($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
        } else {
            $result =  $this->getSummaryReportMySql($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
        }

        // Summary report result in chart
        foreach ($result['result'] as $row) {
            // Label
            $tsLabel = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s');

            if ($reportFilter == '') {
                $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                if ($groupByFilter == 'byweek') {
                    $weekEnd = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('Y-m-d');
                    $weekNo = $row['weekNo'];

                    if ($weekEnd >= $toDt){
                        $weekEnd = $this->getDate()->parse($toDt, 'Y-m-d H:i:s')->format('Y-m-d');
                    }
                    $tsLabel .= ' - ' . $weekEnd. ' (w'.$weekNo.')';
                } elseif ($groupByFilter == 'bymonth') {
                    $tsLabel = __($row['shortMonth']) . ' '. $row['yearDate'];

                }

            } elseif (($reportFilter == 'today') || ($reportFilter == 'yesterday')) {
                $tsLabel = $tsLabel->format('g:i A'); // hourly format (default)

            } elseif(($reportFilter == 'lastweek') || ($reportFilter == 'thisweek') ) {
                $tsLabel = $tsLabel->format('D'); // Mon, Tues, etc.  by day (default)

            } elseif (($reportFilter == 'thismonth') || ($reportFilter == 'lastmonth')) {
                $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                if ($groupByFilter == 'byweek') {
                    $weekEnd = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('Y-m-d');
                    $weekNo = $row['weekNo'];

                    $startInMonth = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('M');
                    $weekEndInMonth = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('M');

                    if ($weekEndInMonth != $startInMonth){
                        $weekEnd = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->endOfMonth()->format('Y-m-d');
                    }

                    $tsLabel = [ $tsLabel . ' - ' . $weekEnd, ' (w'.$weekNo.')'];
                }

            }  elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                $tsLabel = __($row['shortMonth']); // Jan, Feb, etc.  by month (default)

                if ($groupByFilter == 'byday') {
                    $tsLabel = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('Y-m-d');

                } elseif ($groupByFilter == 'byweek') {
                    $weekStart = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('M d');
                    $weekEnd = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('M d');
                    $weekNo = $row['weekNo'];

                    $weekStartInYear = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('Y');
                    $weekEndInYear = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('Y');

                    if ($weekEndInYear != $weekStartInYear){
                        $weekEnd = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->endOfYear()->format('M-d');
                    }
                    $tsLabel = $weekStart . ' - ' . $weekEnd. ' (w'.$weekNo.')';
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

        // Return data to build chart
        return [
            'periodStart' => $result['periodStart'],
            'periodEnd' => $result['periodEnd'],
            'labels' => $labels,
            'countData' => $countData,
            'durationData' => $durationData,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
        ];

    }

    function getSummaryReportMySql($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != '')) ) {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('Y-m-d H:i:s');;
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('Y-m-d H:i:s'); // added a day

            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('Y-m-d H:i:s');
            $today = $this->dateService->parse()->startOfDay()->format('Y-m-d H:i:s');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $firstdaythisweek = $this->dateService->parse()->startOfWeek()->format('Y-m-d H:i:s');
            $lastdaythisweek = $this->dateService->parse()->endOfWeek()->addSecond()->format('Y-m-d H:i:s'); // added a second

            $firstdaylastweek = $this->dateService->parse()->startOfWeek()->subWeek()->format('Y-m-d H:i:s');
            $lastdaylastweek = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('Y-m-d H:i:s'); // added a second

            $firstdaythismonth = $this->dateService->parse()->startOfMonth()->format('Y-m-d H:i:s');
            $lastdaythismonth = $this->dateService->parse()->endOfMonth()->addSecond()->format('Y-m-d H:i:s'); // added a second

            $firstdaylastmonth = $this->dateService->parse()->startOfMonth()->subMonth()->format('Y-m-d H:i:s');
            $lastdaylastmonth = $this->dateService->parse()->endOfMonth()->addSecond()->subMonth()->format('Y-m-d H:i:s');

            $firstdaythisyear = $this->dateService->parse()->startOfYear()->format('Y-m-d H:i:s');
            $lastdaythisyear = $this->dateService->parse()->endOfYear()->addSecond()->format('Y-m-d H:i:s');

            $firstdaylastyear = $this->dateService->parse()->startOfYear()->subYear()->format('Y-m-d H:i:s');
            $lastdaylastyear = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('Y-m-d H:i:s');

            $select = ' 
            
            SELECT 
                B.weekStart,
                B.weekEnd,
                B.weekNo,
                DATE_FORMAT(STR_TO_DATE(MONTH(start), \'%m\'), \'%b\') AS shortMonth, 
                MONTH(start) as monthNo, 
                YEAR(start) as yearDate, 
                start, 
                SUM(count) as NumberPlays, 
                CONVERT(SUM(B.actualDiff), SIGNED INTEGER) as Duration  
            
            FROM (
                    
                SELECT
                     *,
                    WEEK(periods.start, 3) AS weekNo,
                    YEARWEEK(periods.start, 3) AS yearWeek,
                    DATE_SUB(periods.start, INTERVAL WEEKDAY(periods.start) DAY) as weekStart,
                    DATE_SUB(DATE_ADD(DATE_SUB(periods.start, INTERVAL WEEKDAY(periods.start) DAY), INTERVAL 1 WEEK), INTERVAL 1 DAY ) as weekEnd,
                    
                    GREATEST(periods.start, statStart) AS actualStart,
                    LEAST(periods.end, statEnd) AS actualEnd,
                    LEAST(stat.duration, UNIX_TIMESTAMP(LEAST(periods.end, statEnd)) - UNIX_TIMESTAMP(GREATEST(periods.start, statStart))) AS actualDiff
                FROM
                ( 
                    SELECT                
                        
            ';

            if ($reportFilter == '') {
                $range = $diffInDays;

                // START FROM TODATE THEN DECREASE BY ONE DAY TILL FROMDATE
                $select .= '  
                DATE_SUB(DATE_SUB("'.$toDt.'", INTERVAL 1 DAY), INTERVAL c.number DAY) AS start,
                    
                DATE_ADD(
                        DATE_SUB(DATE_SUB("'.$toDt.'", INTERVAL 1 DAY) , INTERVAL c.number DAY), 
                        INTERVAL 1 DAY) AS end ';

            } elseif (($reportFilter == 'today')) {
                $range = 23;

                // START FROM LASTHOUR OF TODAY THEN DECREASE BY ONE HOUR
                $select .= '  
                DATE_FORMAT("'.$today.'",
                        \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR AS start,
                DATE_ADD(DATE_FORMAT("'.$today.'",
                            \'%Y-%m-%d 23:00:00\'),
                    INTERVAL 1 HOUR) - INTERVAL c.number HOUR AS end ';

            } elseif (($reportFilter == 'yesterday')) {
                $range = 23;

                // START FROM LASTHOUR OF YESTERDAY THEN DECREASE BY ONE HOUR
                $select .= '  
                DATE_FORMAT("'.$yesterday.'",
                        \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR AS start,
                DATE_ADD(DATE_FORMAT("'.$yesterday.'",
                            \'%Y-%m-%d 23:00:00\'),
                    INTERVAL 1 HOUR) - INTERVAL c.number HOUR AS end ';

            } elseif (($reportFilter == 'thisweek')) {
                $range = 6;

                // START FROM (LASTDAY OF THISWEEK - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '
                DATE_SUB(DATE_SUB("'.$lastdaythisweek.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaythisweek.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'lastweek')) {
                $range = 6;

                // START FROM (LASTDAY OF LASTWEEK - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '
                DATE_SUB(DATE_SUB("'.$lastdaylastweek.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,
                    
                DATE_SUB("'.$lastdaylastweek.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'thismonth')) {
                $range = 30;

                // START FROM (LASTDAY OF THISMONTH - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaythismonth.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaythismonth.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'lastmonth')) {
                $range = 30;

                // START FROM (LASTDAY OF LASTMONTH - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaylastmonth.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaylastmonth.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'thisyear')) {
                $range = 365;

                // START FROM (LASTDAY OF THISYEAR - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaythisyear.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaythisyear.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'lastyear')) {
                $range = 365;

                // START FROM (LASTDAY OF LASTYEAR - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaylastyear.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaylastyear.'",  INTERVAL c.number DAY) AS end ';
            }

            $periods = '            
                FROM               
                (SELECT 
                    singles + tens + hundreds number
                FROM
                    (SELECT 0 singles UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 
                    UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) singles
                    JOIN (SELECT 0 tens UNION ALL SELECT 10 UNION ALL SELECT 20 UNION ALL SELECT 30 UNION ALL SELECT 40 UNION ALL SELECT 50 
                    UNION ALL SELECT 60 UNION ALL SELECT 70 UNION ALL SELECT 80 UNION ALL SELECT 90) tens
                    JOIN (SELECT 0 hundreds UNION ALL SELECT 100 UNION ALL SELECT 200 UNION ALL SELECT 300 UNION ALL SELECT 400 UNION ALL SELECT 500 
                    UNION ALL SELECT 600 UNION ALL SELECT 700 UNION ALL SELECT 800 UNION ALL SELECT 900) hundreds
                    ORDER BY number DESC) c
                    WHERE
                    c.number BETWEEN 0 AND ' . $range . '
                ) periods        
            ';


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

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {
                $body .= ' AND `stat`.type = \'layout\' 
                       AND `stat`.campaignId = (SELECT campaignId FROM layouthistory WHERE layoutId = ' . $layoutId. ') ';
            } elseif (($type == 'media') && ($mediaId != '')) {
                $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 
                       AND `stat`.mediaId = ' . $mediaId;
            } elseif (($type == 'event') && ($eventTag != '')) {
                $body .= ' AND `stat`.type = \'event\'  
                       AND `stat`.tag = ' . $eventTag;
            }

            $params = [
                'fromDt' => $fromDt,
                'toDt' => $toDt
            ];

            if ($reportFilter == '') {
                $periodStart = $fromDt;
                $periodEnd = $toDt;

            } elseif (($reportFilter == 'today')) {
                $periodStart = $today;
                $periodEnd = $nextday;

            } elseif (($reportFilter == 'yesterday')) {
                $periodStart = $yesterday;
                $periodEnd = $today;

            } elseif (($reportFilter == 'thisweek')) {
                $periodStart = $firstdaythisweek;
                $periodEnd = $lastdaythisweek;

            } elseif (($reportFilter == 'lastweek')) {
                $periodStart = $firstdaylastweek;
                $periodEnd = $lastdaylastweek;

            } elseif (($reportFilter == 'thismonth')) {
                $periodStart = $firstdaythismonth;
                $periodEnd = $lastdaythismonth;

            } elseif (($reportFilter == 'lastmonth')) {
                $periodStart = $firstdaylastmonth;
                $periodEnd = $lastdaylastmonth;

            } elseif (($reportFilter == 'thisyear')) {
                $periodStart = $firstdaythisyear;
                $periodEnd = $lastdaythisyear;

            } elseif (($reportFilter == 'lastyear')) {
                $periodStart = $firstdaylastyear;
                $periodEnd = $lastdaylastyear;
            }

            // where start is less than last day/hour of the period
            // and end is greater than or equal first day/hour of the period
			$body .= ' AND stat.`start` <  "'.$periodEnd.'"
            AND stat.`end` >= "'.$periodStart.'" ';

            $body .= ' ) stat               
            ON statStart < periods.`end`
            AND statEnd > periods.`start`
            ';

            // e.g.,
            // where periods start is greater than or equal today and
            // periods end is less than or equal today + 1 day i.e. nextday
            $body .= ' WHERE periods.`start` >= "'.$periodStart.'"
            AND periods.`end` <= "'.$periodEnd.'" ';

            // ORDER BY
            $body .= '  
            ORDER BY periods.`start`, statStart
	        ) B ';


            if ($groupByFilter == 'byweek') {
                $body .= '  
                    GROUP BY yearWeek ';
            } elseif ($groupByFilter == 'bymonth') {

                if (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $body .= '  
                        GROUP BY monthNo
                        ORDER BY monthNo ';
                } else {
                    $body .= '  
                        GROUP BY yearDate, monthNo ';
                }

            } else {
                $body .= '  
                    GROUP BY B.start ';
            }


            /*Execute sql statement*/
            $query = $select . $periods . $body;
            $this->getLog()->debug($query);

            $options['query'] = $query;
            $options['params'] = $params;

            $results = $this->getTimeSeriesStore()->executeQuery($options);

            return [
                'result' => $results,
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd
            ];

        } else {
            return [];
        }
    }

    function getSummaryReportMongoDb($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != '')) ) {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay();
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay();

            $yesterday = $this->dateService->parse()->startOfDay()->subDay();
            $today = $this->dateService->parse()->startOfDay();
            $nextday = $this->dateService->parse()->startOfDay()->addDay();

            $firstdaythisweek = $this->dateService->parse()->startOfWeek();
            $lastdaythisweek = $this->dateService->parse()->endOfWeek()->addSecond();

            $firstdaylastweek = $this->dateService->parse()->startOfWeek()->subWeek();
            $lastdaylastweek = $this->dateService->parse()->endOfWeek()->subWeek()->addSecond();

            $firstdaythismonth = $this->dateService->parse()->startOfMonth();
            $lastdaythismonth = $this->dateService->parse()->endOfMonth()->addSecond();

            $firstdaylastmonth = $this->dateService->parse()->startOfMonth()->subMonth();
            $lastdaylastmonth = $this->dateService->parse()->endOfMonth()->subMonth()->addSecond();

            $firstdaythisyear = $this->dateService->parse()->startOfYear();
            $lastdaythisyear = $this->dateService->parse()->endOfYear()->addSecond();

            $firstdaylastyear = $this->dateService->parse()->startOfYear()->subYear();
            $lastdaylastyear = $this->dateService->parse()->endOfYear()->subYear()->addSecond();

            if ($reportFilter == '') {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0,  ceil($diffInDays / 7 ) );
                } elseif ($groupByFilter == 'bymonth') {
                    $startOfMonthFromDt = new UTCDateTime($this->dateService->parse($fromDt)->startOfMonth()->format('U')*1000);
                    $input = range(0, ceil($diffInDays / 30));
                } else {
                    $input = range(0, $diffInDays);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($fromDt)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($toDt)->format('U')*1000);
            }

            // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'today')) {

                $hour = 1;
                $input = range(0, 23);

                $periodStart = new UTCDateTime($this->dateService->parse($today)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($nextday)->format('U')*1000);
            }

            // where start is less than last hour of the day + 1 hour (i.e., today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'yesterday')) {

                $hour = 1;
                $input = range(0, 23);

                $periodStart = new UTCDateTime($this->dateService->parse($yesterday)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($today)->format('U')*1000);
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'thisweek')) {

                $hour = 24;
                $input = range(0, 6);

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaythisweek)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaythisweek)->format('U')*1000);
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'lastweek')) {

                $hour = 24;
                $input = range(0, 6);

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaylastweek)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaylastweek)->format('U')*1000);
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'thismonth')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 5);
                } else {
                    $input = range(0, 30);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaythismonth)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaythismonth)->format('U')*1000);
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'lastmonth')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 5);
                } else {
                    $input = range(0, 30);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaylastmonth)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaylastmonth)->format('U')*1000);
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'thisyear')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 53);
                } elseif ($groupByFilter == 'bymonth') {
                    $input = range(0, 11);
                } else {
                    $input = range(0, 365);
                }


                $periodStart = new UTCDateTime($this->dateService->parse($firstdaythisyear)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaythisyear)->format('U')*1000);
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'lastyear')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 53);
                } elseif ($groupByFilter == 'bymonth') {
                    $input = range(0, 11);
                } else {
                    $input = range(0, 365);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaylastyear)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaylastyear)->format('U')*1000);
            }

            $this->getLog()->debug('Period start: '.$periodStart->toDateTime()->format('Y-m-d H:i:s'). ' Period end: '. $periodEnd->toDateTime()->format('Y-m-d H:i:s'));

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
            }


            if ($groupByFilter == 'byweek') {

                // PERIOD GENERATION
                $projectMap = [
                    '$project' => [
                        'periods' =>  [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$max' => [
                                            $periodStart,
                                            [
                                                '$add' => [
                                                    [
                                                        '$subtract' => [
                                                            $periodStart,
                                                            [
                                                                '$multiply' => [
                                                                    [
                                                                        '$subtract' => [
                                                                            [
                                                                                '$isoDayOfWeek' => $periodStart
                                                                            ],
                                                                            1
                                                                        ]
                                                                    ] ,
                                                                    86400000
                                                                ]
                                                            ]
                                                        ]
                                                    ],
                                                    [
                                                        '$multiply' => [
                                                            '$$number',
                                                            604800000
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$min' => [
                                            $periodEnd,
                                            [
                                                '$add' => [
                                                    [
                                                        '$add' => [
                                                            [
                                                                '$subtract' => [
                                                                    $periodStart,
                                                                    [
                                                                        '$multiply' => [
                                                                            [
                                                                                '$subtract' => [
                                                                                    [
                                                                                        '$isoDayOfWeek' => $periodStart
                                                                                    ],
                                                                                    1
                                                                                ]
                                                                            ] ,
                                                                            86400000
                                                                        ]
                                                                    ]
                                                                ]
                                                            ],
                                                            604800000
                                                        ]
                                                    ],
                                                    [
                                                        '$multiply' => [
                                                            '$$number',
                                                            604800000
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];

                // GROUP BY
                $groupBy = [
                    'weekNo' => '$weekNo',
                ];

            } elseif ($groupByFilter == 'bymonth') {

                // period start becomes start of the month of the selected from date in the case of date range selection
                if ($reportFilter != '') {
                    $startOfMonthFromDt = $periodStart;
                }

                $projectMap = [
                    '$project' => [
                        'periods' => [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$dateFromParts' => [
                                            'year' => [
                                                '$year' => $startOfMonthFromDt
                                            ],
                                            'month' => [
                                                '$add' => [
                                                    '$$number',
                                                    [
                                                        '$month' => [
                                                            '$add' => [
                                                                $startOfMonthFromDt,
                                                                [
                                                                    '$multiply' => [
                                                                        $hour * 3600000,
                                                                        '$$number'
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            'day' => [
                                                '$dayOfMonth' => $startOfMonthFromDt
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$dateFromParts' => [
                                            'year' => [
                                                '$year' => $startOfMonthFromDt
                                            ],
                                            'month' => [
                                                '$add' => [
                                                    1,
                                                    [
                                                        '$add' => [
                                                            '$$number',
                                                            [
                                                                '$month' => [
                                                                    '$add' => [
                                                                        $startOfMonthFromDt,
                                                                        [
                                                                            '$multiply' => [
                                                                                $hour * 3600000,
                                                                                '$$number'
                                                                            ]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            'day' => [
                                                '$dayOfMonth' => $startOfMonthFromDt
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];

                // GROUP BY
                $groupBy = [
                    'monthNo' => '$monthNo'
                ];

            } else {

                // PERIOD GENERATION
                $projectMap = [
                    '$project' => [
                        'periods' =>  [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$add' => [
                                            $periodStart,
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
                                                    $periodStart,
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
                                ]
                            ]
                        ]
                    ]
                ];

                // GROUP BY
                $groupBy = [
                    'period_start' => '$period_start'
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
                            'monthNo' => [
                                '$month' =>  '$start'
                            ],
                            'yearDate' => [
                                '$isoWeekYear' =>  '$start'
                            ],
                            'weekNo' => [
                                '$isoWeek' =>  '$start'
                            ],
                        ]
                    ],

                    [
                        '$addFields' => [
                            'shortMonth' => [
                                '$let' => [
                                    'vars' => [
                                        'monthString' => ['NA', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                                    ],
                                    'in' => [
                                        '$arrayElemAt' => [
                                            '$$monthString', '$monthNo'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],

                    [
                        '$match' => [
                            'start' => [
                                '$lt' => $periodEnd
                            ],
                            'end' => [
                                '$gt' => $periodStart
                            ]
                        ]
                    ],

                ];

            // Periods result
            $periods = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->periodTable, 'query' => $cursorPeriodQuery]);

            // STAT AGGREGATION QUERY
            $statQuery = [
                [
                    '$match' => [
                        'start' =>  [
                            '$lt' => $periodEnd
                        ],
                        'end' => [
                            '$gt' => $periodStart
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
                            '$max' => [ '$start', '$statdata.periods.start' ]
                        ],
                        'actualEnd' => [
                            '$min' => [ '$end', '$statdata.periods.end' ]
                        ],
                        'actualDiff' => [
                            '$min' => [
                                '$duration',
                                [
                                    '$divide' => [
                                        [
                                            '$subtract' => [
                                                ['$min' => [ '$end', '$statdata.periods.end' ]],
                                                ['$max' => [ '$start', '$statdata.periods.start' ]]
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
                        'monthNo' => ['$first' => '$monthNo'],
                        'yearDate' => ['$first' => '$yearDate'],
                        'weekNo' => ['$first' => '$weekNo'],
                        'NumberPlays' => ['$sum' => '$count'],
                        'Duration' => ['$sum' => '$actualDiff'],
                        'end' => ['$first' => '$end'],
                    ]
                ],

                [
                    '$project' => [
                        'start' => '$_id.period_start',
                        'end' => '$end',
                        'period_start' => 1,
                        'NumberPlays' => 1,
                        'Duration' => 1,
                        'monthNo' => 1,
                        'yearDate' => 1,
                        'weekNo' => 1,
                    ]
                ],

                // mongodb doesnot have monthname
                // so we use addFields to add month name (shortMonth) in a $let aggregation (map monthNo with monthString)
                [
                    '$addFields' => [
                        'shortMonth' => [
                            '$let' => [
                                'vars' => [
                                    'monthString' => ['NA', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                                ],
                                'in' => [
                                    '$arrayElemAt' => [
                                        '$$monthString', '$monthNo'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],

            ];

            // Stats result
            $results = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->table, 'query' => $statQuery]);

            // Run period loop and map the stat result for each period
            $resultArray = [];
            foreach ($periods as $key => $period) {

                // Format to datetime string
                $start = $period['start']->toDateTime()->format('Y-m-d H:i:s');

                // end is weekEnd in byweek filter
                $end = $period['end']->toDateTime()->format('Y-m-d H:i:s');

                $matched = false;
                foreach ($results as $k => $result) {

                    if( $result['period_start'] == $period['start'] ) {

                        $NumberPlays = $result['NumberPlays'];
                        $Duration = $result['Duration'];
                        $monthNo = $result['monthNo'];
                        $yearDate = $result['yearDate'];
                        $weekNo = $result['weekNo'];
                        $shortMonth = $result['shortMonth'];

                        $matched = true;
                        break;
                    }
                }

                $resultArray[$key]['start'] = $start;

                // end is weekEnd in byweek filter
                if ($groupByFilter == 'byweek') {
                    $resultArray[$key]['weekEnd'] = $end;
                }

                if($matched == true) {
                    $resultArray[$key]['NumberPlays'] = $NumberPlays;
                    $resultArray[$key]['Duration'] = $Duration;
                    $resultArray[$key]['monthNo'] = $monthNo;
                    $resultArray[$key]['yearDate'] = $yearDate;
                    $resultArray[$key]['weekNo'] = $weekNo;
                    $resultArray[$key]['shortMonth'] = $shortMonth;

                } else {
                    $resultArray[$key]['NumberPlays'] = 0;
                    $resultArray[$key]['Duration'] = 0;
                    $resultArray[$key]['monthNo'] = $period['monthNo'];
                    $resultArray[$key]['yearDate'] = $period['yearDate'];
                    $resultArray[$key]['weekNo'] = $period['weekNo'];
                    $resultArray[$key]['shortMonth'] = $period['shortMonth'];

                }
            }

            return [
                'result' => $resultArray,
                'periodStart' => $periodStart->toDateTime()->format('Y-m-d H:i:s'),
                'periodEnd' => $periodEnd->toDateTime()->format('Y-m-d H:i:s')
            ];

        } else {
            return [];
        }
    }
}