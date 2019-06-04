<?php

namespace Xibo\Report;

use Jenssegers\Date\Date;
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
        $this->getLog()->debug('Timeseries store is ' . $timeSeriesStore);

        if ($timeSeriesStore == 'mongodb') {
            $result = $this->getSummaryReportMongoDb($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
        } else {
            $result = $this->getSummaryReportMySql($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
        }

        // Summary report result in chart
        if (count($result) > 0) {
            foreach ($result['result'] as $row) {
                // Label
                $tsLabel = $this->getDate()->parse($row['start'], 'U');

                if ($reportFilter == '') {
                    $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                    if ($groupByFilter == 'byweek') {
                        $start = $this->getDate()->parse($fromDt, 'Y-m-d H:i:s')->startOfDay()->format('U');
                        $end = $this->getDate()->parse($toDt, 'Y-m-d H:i:s')->startOfDay()->addDay()->format('U');

                        $weekEnd = $this->getDate()->parse($row['end'], 'U')->format('Y-m-d');
                        $weekNo = $this->getDate()->parse($row['start'], 'U')->format('W');

                        if ($row['start'] < $start) {
                            $tsLabel = $this->getDate()->parse($start, 'U')->format('Y-m-d');
                        }
                        // last day of the month in chart
                        if ($row['end'] > $end) {
                            $weekEnd = $this->getDate()->parse($end, 'U')->format('Y-m-d');
                        }

                        $tsLabel .= ' - ' . $weekEnd . ' (w' . $weekNo . ')';
                    } elseif ($groupByFilter == 'bymonth') {

                        $tsLabel = $this->getDate()->parse($row['start'], 'U')->format('M') . ' ' . $this->getDate()->parse($row['start'], 'U')->format('Y');
                    }

                } elseif (($reportFilter == 'today') || ($reportFilter == 'yesterday')) {
                    $tsLabel = $tsLabel->format('g:i A'); // hourly format (default)

                } elseif (($reportFilter == 'lastweek') || ($reportFilter == 'thisweek')) {
                    $tsLabel = $tsLabel->format('D'); // Mon, Tues, etc.  by day (default)

                } elseif (($reportFilter == 'thismonth') || ($reportFilter == 'lastmonth')) {
                    $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                    if ($groupByFilter == 'byweek') {
                        $weekEnd = $this->getDate()->parse($row['end'], 'U')->format('Y-m-d');
                        $weekNo = $this->getDate()->parse($row['start'], 'U')->format('W');

                        // first day of the month in chart
                        if ($reportFilter == 'thismonth') {
                            $startOfMonth = $this->getDate()->parse()->startOfMonth()->format('U');
                            $endOfMonth = $this->getDate()->parse()->endOfMonth()->format('U');
                        } else {
                            $startOfMonth = $this->getDate()->parse()->startOfMonth()->subMonth()->format('U');
                            $endOfMonth = $this->getDate()->parse()->startOfMonth()->subMonth()->endOfMonth()->format('U');
                        }

                        if ($row['start'] < $startOfMonth) {
                            $tsLabel = $this->getDate()->parse($startOfMonth, 'U')->format('Y-m-d');
                        }

                        // last day of the month in chart
                        if ($row['end'] > $endOfMonth) {
                            $weekEnd = $this->getDate()->parse($endOfMonth, 'U')->format('Y-m-d');
                        }

                        $tsLabel = [$tsLabel . ' - ' . $weekEnd, ' (w' . $weekNo . ')'];
                    }

                } elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $tsLabel = $tsLabel->format('M');// Jan, Feb, etc.  by month (default)

                    if ($groupByFilter == 'byday') {
                        $tsLabel = $this->getDate()->parse($row['start'], 'U')->format('Y-m-d');

                    } elseif ($groupByFilter == 'byweek') {
                        $tsLabel = $this->getDate()->parse($row['start'], 'U')->format('M d');// Jan, Feb, etc.  by month (default)

                        $weekEnd = $this->getDate()->parse($row['end'], 'U')->format('M d');
                        $weekNo = $this->getDate()->parse($row['start'], 'U')->format('W');

                        // first day of the year in chart
                        if ($reportFilter == 'thisyear') {
                            $startOfYear = $this->getDate()->parse()->startOfYear()->format('U');
                            $endOfYear = $this->getDate()->parse()->endOfYear()->format('U');
                        } else {
                            $startOfYear = $this->getDate()->parse()->startOfYear()->subYear()->format('U');
                            $endOfYear = $this->getDate()->parse()->startOfYear()->subYear()->endOfYear()->format('U');
                        }

                        if ($row['start'] < $startOfYear) {
                            $tsLabel = $this->getDate()->parse($startOfYear, 'U')->format('M-d');
                        }

                        if ($row['end'] > $endOfYear) {
                            $weekEnd = $this->getDate()->parse($endOfYear, 'U')->format('M-d');
                        }
                        $tsLabel = $tsLabel . ' - ' . $weekEnd . ' (w' . $weekNo . ')';
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

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('U');
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('U');


            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('U');
            $today = $this->dateService->parse()->startOfDay()->format('U');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('U');

            if ($reportFilter == '') {
                $filterRangeStart = $fromDt;
                $filterRangeEnd = $toDt;

                if ($groupByFilter == 'byday') {
                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                } elseif ($groupByFilter == 'byweek') {

                    // Extend the range upto the start of the week of the range start, and end of the week of the range end
                    $extendedPeriodStart = $this->dateService->parse($fromDt, 'U')->startOfWeek()->format('U') ;
                    $extendedPeriodEnd = $this->dateService->parse($toDt, 'U')->endOfWeek()->addSecond()->format('U');

                } elseif ($groupByFilter == 'bymonth') {

                    $months = range(0,  ceil($diffInDays / 30 ) );

                    // We extend the fromDt and toDt range filter
                    // so that we can generate each month period
                    $fromDtStartOfMonth = $this->dateService->parse($fromDt, 'U')->startOfMonth();
                    $toDtEndOfMonth = $this->dateService->parse($toDt, 'U')->endOfMonth()->addSecond();

                    // Generate all months that lie in the extended range
                    $monthperiods = [];
                    foreach ($months as $key => $month) {

                        $monthPeriodStart = $this->dateService->parse($fromDtStartOfMonth)->addMonth($key)->format('U');
                        $monthPeriodEnd = $this->dateService->parse($fromDtStartOfMonth)->addMonth($key)->addMonth()->format('U');

                        // Remove the month period which crossed the extended end range
                        if ($monthPeriodStart >= $toDtEndOfMonth->format('U')) {
                            continue;
                        }
                        $monthperiods[$key]['start'] =  $monthPeriodStart;
                        $monthperiods[$key]['end'] =    $monthPeriodEnd;
                    }

                    $extendedPeriodStart = $fromDtStartOfMonth->format('U');
                    $extendedPeriodEnd = $toDtEndOfMonth->format('U');
                }

            } elseif (($reportFilter == 'today')) {
                $filterRangeStart = $today;
                $filterRangeEnd = $nextday;

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

            } elseif (($reportFilter == 'yesterday')) {
                $filterRangeStart = $yesterday;
                $filterRangeEnd = $today;

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

            } elseif (($reportFilter == 'thisweek')) {

                $filterRangeStart = $this->dateService->parse()->startOfWeek()->format('U');  //firstdaythisweek
                $filterRangeEnd = $this->dateService->parse()->endOfWeek()->addSecond()->format('U');//lastdaythisweek

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

            } elseif (($reportFilter == 'lastweek')) {

                $filterRangeStart = $this->dateService->parse()->startOfWeek()->subWeek()->format('U'); //firstdaylastweek
                $filterRangeEnd = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('U'); //lastdaylastweek

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

            } elseif (($reportFilter == 'thismonth')) {

                $filterRangeStart = $this->dateService->parse()->startOfMonth()->format('U'); //firstdaythismonth
                $filterRangeEnd = $this->dateService->parse()->endOfMonth()->addSecond()->format('U'); //lastdaythismonth

                if ($groupByFilter == 'byday') {
                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;
                } elseif ($groupByFilter == 'byweek') {
                    // Extend the range upto the start of the week of the month, and end of the week of the month
                    $extendedPeriodStart = $this->dateService->parse()->startOfMonth()->startOfWeek()->format('U');
                    $extendedPeriodEnd = $this->dateService->parse()->endOfMonth()->endOfWeek()->addSecond()->format('U');
                }

            } elseif (($reportFilter == 'lastmonth')) {

                $filterRangeStart = $this->dateService->parse()->startOfMonth()->subMonth()->format('U'); //firstdaylastmonth
                $filterRangeEnd = $this->dateService->parse()->startOfMonth()->subMonth()->endOfMonth()->addSecond()->format('U'); //lastdaylastmonth

                if ($groupByFilter == 'byday') {
                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;
                } elseif ($groupByFilter == 'byweek') {
                    // Extend the range upto the start of the week of the month, and end of the week of the month
                    $extendedPeriodStart = $this->dateService->parse()->startOfMonth()->subMonth()->startOfWeek()->format('U');
                    $extendedPeriodEnd = $this->dateService->parse()->endOfMonth()->subMonth()->endOfWeek()->addSecond()->format('U');
                }

            }  elseif (($reportFilter == 'thisyear')) {

                $filterRangeStart = $this->dateService->parse()->startOfYear()->format('U'); //firstdaythisyear
                $filterRangeEnd = $this->dateService->parse()->endOfYear()->addSecond()->format('U'); //lastdaythisyear

                if ($groupByFilter == 'byday') {
                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;
                } elseif ($groupByFilter == 'byweek') {
                    // Extend the range upto the start of the first week of the year, and end of the week of the year
                    $extendedPeriodStart = $this->dateService->parse()->startOfYear()->startOfWeek()->format('U');
                    $extendedPeriodEnd = $this->dateService->parse()->endOfYear()->endOfWeek()->addSecond()->format('U');
                } elseif ($groupByFilter == 'bymonth') {

                    $monthperiods = [];
                    $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
                    $start = $this->dateService->parse()->startOfYear()->subMonth()->startOfMonth();
                    $end = $this->dateService->parse()->startOfYear()->startOfMonth();
                    // Generate all 12 months
                    foreach ($months as $month) {
                        $monthperiods[$month]['start'] = $start->addMonth()->format('U');
                        $monthperiods[$month]['end'] = $end->addMonth()->format('U');
                    }

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                }
            } elseif (($reportFilter == 'lastyear')) {

                $filterRangeStart = $this->dateService->parse()->startOfYear()->subYear()->format('U'); //firstdaylastyear
                $filterRangeEnd = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('U'); //lastdaylastyear

                if ($groupByFilter == 'byday') {
                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;
                } elseif ($groupByFilter == 'byweek') {
                    // Extend the range upto the start of the first week of the year, and end of the week of the year
                    $extendedPeriodStart = $this->dateService->parse()->startOfYear()->subYear()->startOfWeek()->format('U');
                    $extendedPeriodEnd = $this->dateService->parse()->endOfYear()->subYear()->endOfWeek()->addSecond()->format('U');

                } elseif ($groupByFilter == 'bymonth') {

                    $monthperiods = [];
                    $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
                    $start = $this->dateService->parse()->subYear()->startOfYear()->subMonth()->startOfMonth();
                    $end = $this->dateService->parse()->subYear()->startOfYear()->startOfMonth();
                    // Generate all 12 months
                    foreach ($months as $month) {
                        $monthperiods[$month]['start'] = $start->addMonth()->format('U');
                        $monthperiods[$month]['end'] = $end->addMonth()->format('U');
                    }

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                }
            }

            // Query starts
            $select = '

            SELECT
                start, end, 
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

            if ($reportFilter == '') {

                if ($groupByFilter == 'byday') {
                    $range = $diffInDays;

                    // START FROM TODATE THEN DECREASE BY ONE DAY TILL FROMDATE
                    $select .= ' 
                    '.$extendedPeriodEnd.' - 86400 - (c.number * 86400)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 86400) AS end ';

                } elseif ($groupByFilter == 'byweek') { //TODO

                    $range = ceil($diffInDays / 7 );

                    // START FROM (LASTDAY OF THE MONTH UP TILL THE WEEKEND - 7 DAYS) THEN DECREASE BY 7 DAYS
                    $select .= ' 
                    '.$extendedPeriodEnd.' - 604800 - (c.number * 604800)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 604800) AS end ';

                }

            } elseif (($reportFilter == 'today') || ($reportFilter == 'yesterday')){
                $range = 23;

                // START FROM LASTHOUR OF TODAY THEN DECREASE BY ONE HOUR
                $select .= ' 
                    '.$extendedPeriodEnd.' - 3600 - (c.number * 3600)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 3600) AS end ';

            } elseif (($reportFilter == 'thisweek') || ($reportFilter == 'lastweek')) {
                $range = 6;

                // START FROM (LASTDAY OF THE WEEK - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= ' 
                '.$extendedPeriodEnd.' - 86400 - (c.number * 86400)  AS start, 
                '.$extendedPeriodEnd.' - (c.number * 86400) AS end ';

            } elseif (($reportFilter == 'thismonth') || ($reportFilter == 'lastmonth')) {
                if ($groupByFilter == 'byday') {

                    $range = 30;

                    // START FROM (LASTDAY OF THE MONTH - 1 DAY) THEN DECREASE BY 1 DAY
                    $select .= ' 
                    '.$extendedPeriodEnd.' - 86400 - (c.number * 86400)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 86400) AS end ';

                } elseif ($groupByFilter == 'byweek') {

                    $range = 4;

                    // START FROM (LASTDAY OF THE MONTH UP TILL THE WEEKEND - 7 DAYS) THEN DECREASE BY 7 DAYS
                    $select .= ' 
                    '.$extendedPeriodEnd.' - 604800 - (c.number * 604800)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 604800) AS end ';

                }

            } elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {

                if ($groupByFilter == 'byday') {
                    $range = 365;

                    // START FROM (LASTDAY OF THE YEAR - 1 DAY) THEN DECREASE BY ONE DAY
                    $select .= ' 
                    '.$extendedPeriodEnd.' - 86400 - (c.number * 86400)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 86400) AS end ';

                } elseif ($groupByFilter == 'byweek') { 

                    $range = 53;

                    // START FROM (LASTDAY OF THE MONTH UP TILL THE WEEKEND - 7 DAYS) THEN DECREASE BY 7 DAYS
                    $select .= ' 
                    '.$extendedPeriodEnd.' - 604800 - (c.number * 604800)  AS start, 
                    '.$extendedPeriodEnd.' - (c.number * 604800) AS end ';
                }
            }

            if ( (($reportFilter == '') || ($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) && ($groupByFilter == 'bymonth'))  {

                $string = ' ';
                $inc =   0;
                foreach ($monthperiods as $p) {

                    $string .= $p['start'].'  start, '.$p['end']. ' end ';

                    $inc++;
                    if ($inc != count($monthperiods)) {
                        $string .= ' UNION ALL SELECT ';
                    }
                }

                $periods = '            
                                ' .$string.'
                ) periods        
            ';

            } else {
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
            }


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
            $body .= ' WHERE periods.`start` >= '.$extendedPeriodStart.'
            AND periods.`end` <= '.$extendedPeriodEnd.' ';

            // ORDER BY
            $body .= '  
            ORDER BY periods.`start`, statStart
	        ) B ';

            // GROUP BY
            $body .= '
            GROUP BY start, end ';

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

    function getSummaryReportMongoDb($displayIds, $diffInDays, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != '')) ) {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('U');
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('U');

            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('U');
            $today = $this->dateService->parse()->startOfDay()->format('U');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('U');

            if ($reportFilter == '') {

                $hour = 24;

                $filterRangeStart = new UTCDateTime($fromDt * 1000);
                $filterRangeEnd = new UTCDateTime($toDt * 1000);

                if ($groupByFilter == 'byday') {
                    $input = range(0, $diffInDays);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                } elseif ($groupByFilter == 'byweek') {
                    $input = range(0,  ceil($diffInDays / 7 ) );

                    // Extend the range upto the start of the week of the range start, and end of the week of the range end
                    $startOfWeek = $this->dateService->parse($fromDt, 'U')->startOfWeek()->format('U');
                    $endOfWeek = $this->dateService->parse($toDt, 'U')->endOfWeek()->addSecond()->format('U');

                    // Formatting
                    $extendedPeriodStart = new UTCDateTime( $startOfWeek * 1000);
                    $extendedPeriodEnd = new UTCDateTime($endOfWeek * 1000);

                } elseif ($groupByFilter == 'bymonth') {

                    $input = range(0, ceil($diffInDays / 30));

                    // We extend the fromDt and toDt range filter
                    // so that we can generate each month period
                    $fromDtStartOfMonth = $this->dateService->parse($fromDt, 'U')->startOfMonth();
                    $toDtEndOfMonth = $this->dateService->parse($toDt, 'U')->endOfMonth()->addSecond();

                    // Generate all months that lie in the extended range
                    $monthperiods = [];
                    foreach ($input as $key => $value) {

                        $monthPeriodStart = $this->dateService->parse($fromDtStartOfMonth)->addMonth($key)->format('U');
                        $monthPeriodEnd = $this->dateService->parse($fromDtStartOfMonth)->addMonth($key)->addMonth()->format('U');

                        // Remove the month period which crossed the extended end range
                        if ($monthPeriodStart >= $toDtEndOfMonth->format('U')) {
                            continue;
                        }
                        $monthperiods[$key]['start'] =  new UTCDateTime( $monthPeriodStart * 1000);
                        $monthperiods[$key]['end'] =    new UTCDateTime( $monthPeriodEnd * 1000);
                    }

                    $extendedPeriodStart = new UTCDateTime( $fromDtStartOfMonth->format('U') * 1000);
                    $extendedPeriodEnd = new UTCDateTime( $toDtEndOfMonth->format('U') * 1000);
                }
            }

            elseif (($reportFilter == 'today')) {

                $hour = 1;
                $input = range(0, 23);

                $filterRangeStart = new UTCDateTime($today * 1000);
                $filterRangeEnd = new UTCDateTime($nextday * 1000);

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

            }

            elseif (($reportFilter == 'yesterday')) {

                $hour = 1;
                $input = range(0, 23);

                $filterRangeStart = new UTCDateTime($yesterday * 1000);
                $filterRangeEnd = new UTCDateTime($today * 1000);

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;
            }

            elseif (($reportFilter == 'thisweek')) {

                $hour = 24;
                $input = range(0, 6);

                $startUTC = $this->dateService->parse()->startOfWeek()->format('U');  //firstdaythisweek
                $endUTC = $this->dateService->parse()->endOfWeek()->addSecond()->format('U');//lastdaythisweek

                $filterRangeStart = new UTCDateTime( $startUTC * 1000);
                $filterRangeEnd = new UTCDateTime( $endUTC * 1000);

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;
            }

            elseif (($reportFilter == 'lastweek')) {

                $hour = 24;
                $input = range(0, 6);

                $startUTC = $this->dateService->parse()->startOfWeek()->subWeek()->format('U'); //firstdaylastweek
                $endUTC = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('U'); //lastdaylastweek

                $filterRangeStart = new UTCDateTime( $startUTC * 1000);
                $filterRangeEnd = new UTCDateTime( $endUTC * 1000);

                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;

            }

            elseif (($reportFilter == 'thismonth')) {

                $hour = 24;

                $startUTC = $this->dateService->parse()->startOfMonth()->format('U'); //firstdaythismonth
                $endUTC =  $this->dateService->parse()->endOfMonth()->addSecond()->format('U'); //lastdaythismonth

                $filterRangeStart = new UTCDateTime( $startUTC * 1000);
                $filterRangeEnd = new UTCDateTime( $endUTC * 1000);

                if ($groupByFilter == 'byday') {
                    $input = range(0, 30);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                } elseif ($groupByFilter == 'byweek') {
                    $input = range(0, 5);

                    $startOfWeek = $this->dateService->parse()->startOfMonth()->startOfWeek()->format('U');
                    $endOfWeek = $this->dateService->parse()->endOfMonth()->endOfWeek()->addSecond()->format('U');

                    // Extend the range upto the start of the week of the month, and end of the week of the month
                    $extendedPeriodStart = new UTCDateTime( $startOfWeek * 1000);
                    $extendedPeriodEnd = new UTCDateTime($endOfWeek * 1000);

                }

            }

            elseif (($reportFilter == 'lastmonth')) {

                $hour = 24;

                $startUTC = $this->dateService->parse()->startOfMonth()->subMonth()->format('U'); //firstdaylastmonth
                $endUTC =  $this->dateService->parse()->startOfMonth()->subMonth()->endOfMonth()->addSecond()->format('U'); //lastdaylastmonth

                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);


                if ($groupByFilter == 'byday') {
                    $input = range(0, 30);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                } elseif ($groupByFilter == 'byweek') {
                    $input = range(0, 5);

                    $startOfWeek = $this->dateService->parse()->startOfMonth()->subMonth()->startOfWeek()->format('U');
                    $endOfWeek = $this->dateService->parse()->endOfMonth()->subMonth()->endOfWeek()->addSecond()->format('U');

                    // Extend the range upto the start of the week of the month, and end of the week of the month
                    $extendedPeriodStart = new UTCDateTime( $startOfWeek * 1000);
                    $extendedPeriodEnd = new UTCDateTime( $endOfWeek * 1000);

                }
            }

            elseif (($reportFilter == 'thisyear')) {

                $hour = 24;

                $startUTC = $this->dateService->parse()->startOfYear()->format('U'); //firstdaythisyear
                $endUTC = $this->dateService->parse()->endOfYear()->addSecond()->format('U'); //lastdaythisyear
                
                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);

                if ($groupByFilter == 'byday') {
                    $input = range(0, 365);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                } elseif ($groupByFilter == 'byweek') {
                    $input = range(0, 53);

                    // Extend the range upto the start of the first week of the year, and end of the week of the year
                    $startOfWeek = $this->dateService->parse()->startOfYear()->startOfWeek()->format('U');
                    $endOfWeek = $this->dateService->parse()->endOfYear()->endOfWeek()->addSecond()->format('U');

                    // Formatting
                    $extendedPeriodStart = new UTCDateTime( $startOfWeek * 1000);
                    $extendedPeriodEnd = new UTCDateTime($endOfWeek * 1000);

                } elseif ($groupByFilter == 'bymonth') { 
                    $input = range(0, 11);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;


                    $start = $this->dateService->parse()->startOfYear()->subMonth()->startOfMonth();
                    $end = $this->dateService->parse()->startOfYear()->startOfMonth();

                    // Generate all 12 months
                    $monthperiods = [];
                    foreach ($input as $key => $value) {
                        $monthperiods[$key]['start'] = new UTCDateTime( $start->addMonth()->format('U') * 1000);
                        $monthperiods[$key]['end'] = new UTCDateTime( $end->addMonth()->format('U') * 1000);
                    }

                }

            }

            elseif (($reportFilter == 'lastyear')) {

                $hour = 24;

                $startUTC = $this->dateService->parse()->startOfYear()->subYear()->format('U'); //firstdaylastyear
                $endUTC = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('U'); //lastdaylastyear

                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);

                if ($groupByFilter == 'byday') {
                    $input = range(0, 365);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                } elseif ($groupByFilter == 'byweek') {
                    $input = range(0, 53);

                    // Extend the range upto the start of the first week of the year, and end of the week of the year
                    $startOfWeek = $this->dateService->parse()->startOfYear()->subYear()->startOfWeek()->format('U');
                    $endOfWeek = $this->dateService->parse()->endOfYear()->subYear()->endOfWeek()->addSecond()->format('U');

                    // Formatting
                    $extendedPeriodStart = new UTCDateTime( $startOfWeek * 1000);
                    $extendedPeriodEnd = new UTCDateTime($endOfWeek * 1000);

                } elseif ($groupByFilter == 'bymonth') { 
                    $input = range(0, 11);

                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                    $start = $this->dateService->parse()->subYear()->startOfYear()->subMonth()->startOfMonth();
                    $end = $this->dateService->parse()->subYear()->startOfYear()->startOfMonth();

                    // Generate all 12 months
                    $monthperiods = [];
                    foreach ($input as $key => $value) {
                        $monthperiods[$key]['start'] = new UTCDateTime( $start->addMonth()->format('U') * 1000);
                        $monthperiods[$key]['end'] = new UTCDateTime( $end->addMonth()->format('U') * 1000);
                    }
                }
            }

            $this->getLog()->debug('Period start: '.$filterRangeStart->toDateTime()->format('Y-m-d H:i:s'). ' Period end: '. $filterRangeEnd->toDateTime()->format('Y-m-d H:i:s'));

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
                                                    604800000
                                                ]
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$add' => [
                                            [
                                                '$add' => [
                                                    $extendedPeriodStart,
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
                                                    $extendedPeriodStart,
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

                $matched = false;
                foreach ($results as $k => $result) {

                    if( $result['period_start'] == $period['start'] ) {

                        $NumberPlays = $result['NumberPlays'];
                        $Duration = $result['Duration'];

                        $matched = true;
                        break;
                    }
                }

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