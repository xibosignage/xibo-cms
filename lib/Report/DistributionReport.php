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

            $saveAs = ucfirst($filterCriteria['filter']). ' report for Layout '. $layout->layout;


        } else if ($filterCriteria['type'] == 'media') {
            try {
                $media = $this->mediaFactory->getById($filterCriteria['mediaId']);
                $saveAs = ucfirst($filterCriteria['filter']). ' report for Media '. $media->name;

            } catch (NotFoundException $error) {
                $saveAs = 'Media not found';
            }

        } else if ($filterCriteria['type'] == 'event') {
            $saveAs = ucfirst($filterCriteria['filter']). ' report for Event '. $filterCriteria['eventTag'];
        }

        if (!empty($filterCriteria['displayId'])) {

            // Get display
            try{
                $displayName = $this->displayFactory->getById($filterCriteria['displayId'])->display;
                $saveAs .= ' (Display: '. $displayName . ')';

            } catch (NotFoundException $error){
                $saveAs .= ' (DisplayId: Not Found )';
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
                $toDt = $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse());

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
            $result = $this->getDistributionReportMongoDb($displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter, $fromDt, $toDt);
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
            foreach ($result as $row) {
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
            // My from/to dt represent the entire range we're interested in.
            // we need to generate periods according to our grouping, within that range.
            // we will use a temporary table for this.
            $periods = '
                CREATE TEMPORARY TABLE temp_periods AS       
            ';

            // Loop until we've covered all periods needed
            $loopDate = $fromDt->copy();
            while ($toDt > $loopDate) {
                // We add different periods for each type of grouping
                if ($groupByFilter == 'byhour') {
                    $periods .= ' 
                        SELECT  ' . $loopDate->hour . ' AS id, 
                            \'' . $loopDate->format('g:i A') . '\' AS label, 
                            ' . $loopDate->format('U') . ' AS start, 
                            ' . $loopDate->addHour()->format('U') . ' AS end ';

                } else if ($groupByFilter == 'bydayofweek') {
                    $periods .= ' 
                        SELECT  ' . $loopDate->dayOfWeek . ' AS id, 
                            \'' . $loopDate->format('D') . '\' AS label, 
                            ' . $loopDate->format('U') . ' AS start, 
                            ' . $loopDate->addDay()->format('U') . ' AS end ';

                } else if ($groupByFilter == 'bydayofmonth') {
                    $periods .= ' 
                        SELECT  ' . $loopDate->day . ' AS id, 
                            \'' . $loopDate->format('d') . '\' AS label, 
                            ' . $loopDate->format('U') . ' AS start, 
                            ' . $loopDate->addDay()->format('U') . ' AS end ';
                } else {
                    $this->getLog()->error('Unknown Grouping Selected ' . $groupByFilter);
                    return [];
                }

                if ($toDt > $loopDate) {
                    $periods .= ' UNION ALL ';
                }
            }

            $this->getStore()->update($periods, []);

            $this->getLog()->debug(json_encode($this->store->select('SELECT * FROM temp_periods', []), JSON_PRETTY_PRINT));

            // Join in stats
            // -------------
            $select = '                      
            SELECT start, end, periodsWithStats.id, periodsWithStats.label,
                SUM(count) as NumberPlays, 
                CONVERT(SUM(periodsWithStats.actualDiff), SIGNED INTEGER) as Duration
             FROM (
                SELECT
                     *,
                    GREATEST(periods.start, statStart, '. $fromDt->format('U') . ') AS actualStart,
                    LEAST(periods.end, statEnd, '. $toDt->format('U') . ') AS actualEnd,
                    LEAST(stat.duration, LEAST(periods.end, statEnd, '. $toDt->format('U') . ') - GREATEST(periods.start, statStart, '. $fromDt->format('U') . ')) AS actualDiff
                 FROM temp_periods AS periods
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

            return $this->getStore()->select($select, $params);

        } else {
            return [];
        }
    }

    function getDistributionReportMongoDb($displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {

        // range - by hour, by dayofweek, by dayofmonth
        // today and yesterday - only by hour
        // thisweek and lastweek - only by hour
        // thismonth and lastmonth - by hour, by dayofweek, by dayofmonth
        // thisyear and lastyear - by dayofweek, by dayofmonth

        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != '')) ) {

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

            }

            elseif (($reportFilter == 'thismonth')) {

                $startUTC = $this->dateService->parse()->startOfMonth()->format('U'); //firstdaythismonth
                $endUTC =  $this->dateService->parse()->endOfMonth()->addSecond()->format('U'); //lastdaythismonth

                $filterRangeStart = new UTCDateTime( $startUTC * 1000);
                $filterRangeEnd = new UTCDateTime( $endUTC * 1000);


                if ($groupByFilter == 'byhour') {
                    $hour = 1;
                    $input = range(0, 24 * 31 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {
                    $hour = 24;
                    $input = range(0, 30);

                } elseif ($groupByFilter == 'bydayofmonth') {
                    $hour = 24;
                    $input = range(0, 30);
                }
            }

            elseif (($reportFilter == 'lastmonth')) {

                $startUTC = $this->dateService->parse()->startOfMonth()->subMonth()->format('U'); //firstdaylastmonth
                $endUTC =  $this->dateService->parse()->startOfMonth()->subMonth()->endOfMonth()->addSecond()->format('U'); //lastdaylastmonth

                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);

                if ($groupByFilter == 'byhour') {
                    $hour = 1;
                    $input = range(0, 24 * 31 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {
                    $hour = 24;
                    $input = range(0, 30);

                } elseif ($groupByFilter == 'bydayofmonth') {
                    $hour = 24;
                    $input = range(0, 30);
                }

            } elseif (($reportFilter == 'thisyear')) {

                $startUTC = $this->dateService->parse()->startOfYear()->format('U'); //firstdaythisyear
                $endUTC = $this->dateService->parse()->endOfYear()->addSecond()->format('U'); //lastdaythisyear

                $filterRangeStart = new UTCDateTime($startUTC * 1000);
                $filterRangeEnd = new UTCDateTime($endUTC * 1000);

                if ($groupByFilter == 'byhour') {
                    $hour = 1;
                    $input = range(0, 24 * 366 - 1);

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
                    $input = range(0, 24 * 366 - 1);

                } elseif ($groupByFilter == 'bydayofweek') {
                    $hour = 24;
                    $input = range(0, 365);

                } elseif ($groupByFilter == 'bydayofmonth') {

                    $hour = 24;
                    $input = range(0, 365);
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