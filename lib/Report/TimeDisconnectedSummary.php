<?php

namespace Xibo\Report;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class TimeDisconnectedSummary
 * @package Xibo\Report
 */
class TimeDisconnectedSummary implements ReportInterface
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

    /**
     * Report Constructor.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param SanitizerService $sanitizer
     */
    public function __construct($state, $store, $timeSeriesStore, $log, $config, $sanitizer)
    {
        $this->setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $sanitizer);
    }

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
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
        return json_encode($results['results']['chart']);
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'timedisconnectedsummary-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'timedisconnectedsummary-report-form',
            'data' =>  [
                'fromDate' => Carbon::now()->subSeconds(86400 * 35)->format(DateFormatHelper::getSystemFormat()),
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                'availableReports' => $this->reportService->listReports()
            ]
        ];
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(Request $request)
    {
        $title = __('Add Report Schedule');

        $data = [];

        $data['formTitle'] = $title;
        $data['reportName'] = 'timedisconnectedsummary';

        return [
            'template' => 'timedisconnectedsummary-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(Request $request)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $filter = $sanitizedParams->getString('filter');
        $displayId = $sanitizedParams->getInt('displayId');

        $filterCriteria['displayId'] = $displayId;
        $filterCriteria['filter'] = $filter;

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';

        } else if ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';

        } else if ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';

        } else if ($filter == 'yearly') {
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
    public function generateSavedReportName($filterCriteria)
    {
        return sprintf(__('%s time disconnected summary report', ucfirst($filterCriteria['filter'])));
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Return data to build chart
        return array_merge($json, [
            'template' => 'timedisconnectedsummary-report-preview',
            'savedReport' => $savedReport,
            'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)->format(DateFormatHelper::getSystemFormat())
        ]);
    }

    /** @inheritdoc */
    public function getResults($filterCriteria)
    {

        $this->getLog()->debug('Filter criteria: '. json_encode($filterCriteria, JSON_PRETTY_PRINT));

        $sanitizedParams = $this->getSanitizer($filterCriteria);

        $displayId = $sanitizedParams->getInt('displayId');
        $displayGroupId = $sanitizedParams->getInt('displayGroupId');
        $tags = $sanitizedParams->getString('tags');
        $onlyLoggedIn = $sanitizedParams->getCheckbox('onlyLoggedIn') == 1;

        $currentDate = Carbon::now()->startOfDay()->format('Y-m-d');

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

        switch ($reportFilter) {

            // the monthly data starts from yesterday
            case 'yesterday':
                $fromDt = $now->copy()->startOfDay()->subDay();
                $toDt = $now->copy()->startOfDay();
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
                $fromDt = $sanitizedParams->getDate('fromDt', ['default' => $sanitizedParams->getDate('availabilityFromDt')]);
                $toDt = $sanitizedParams->getDate('toDt', ['default' => $sanitizedParams->getDate('availabilityToDt')]);

                // fromDt is always start of selected day
                $fromDt = $fromDt->startOfDay();

                // If toDt is current date then make it current datetime
                if ($toDt->startOfDay()->format('Y-m-d') == $currentDate) {
                    $toDt = Carbon::now();
                } else {
                    $toDt =  Carbon::now()->startOfDay();
                }

                break;
        }

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
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

        $params = array(
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        );

        $select = '
            SELECT display.display, display.displayId,
            SUM(LEAST(IFNULL(`end`, :end), :end) - GREATEST(`start`, :start)) AS duration,
            :end - :start as filter ';

        if ($tags != '') {
            $select .= ', (SELECT GROUP_CONCAT(DISTINCT tag)
              FROM tag
                INNER JOIN lktagdisplaygroup
                  ON lktagdisplaygroup.tagId = tag.tagId
                WHERE lktagdisplaygroup.displayGroupId = displaygroup.DisplayGroupID
                GROUP BY lktagdisplaygroup.displayGroupId) AS tags ';
        }

        $body = 'FROM `displayevent`
                INNER JOIN `display`
                ON display.displayId = `displayevent`.displayId ';

        if ($displayGroupId != 0) {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid ';
        }

        if ($tags != '') {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid
                     INNER JOIN `displaygroup`
                        ON displaygroup.displaygroupId = lkdisplaydg.displaygroupId
                         AND `displaygroup`.isDisplaySpecific = 1 ';
        }

        $body .= 'WHERE `start` <= :end
                  AND IFNULL(`end`, :end) >= :start
                  AND :end <= UNIX_TIMESTAMP(NOW())
                  AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayGroupId != 0) {
            $body .= '
                     AND lkdisplaydg.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = $displayGroupId;
        }

        if ($tags != '') {
            if (trim($tags) === '--no-tag') {
                $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $sanitizedParams->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND `displaygroup`.displaygroupId IN (
                SELECT `lktagdisplaygroup`.displaygroupId
                  FROM tag
                    INNER JOIN `lktagdisplaygroup`
                    ON `lktagdisplaygroup`.tagId = tag.tagId
                ";
                $i = 0;

                foreach (explode(',', $tags) as $tag) {
                    $i++;

                    if ($i == 1)
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;

                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }

                $body .= " ) ";
            }
        }

        if ($displayId != 0) {
            $body .= ' AND display.displayId = :displayId ';
            $params['displayId'] = $displayId;
        }

        if ($onlyLoggedIn) {
            $body .= ' AND `display`.loggedIn = 1 ';
        }

        $body .= '
            GROUP BY display.display
        ';

        // Sorting?
        $filterBy = $this->gridRenderFilter($filterCriteria);
        $sortOrder = $this->gridRenderSort($filterCriteria);

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';

        // Paging
        $filterBy = $this->getSanitizer($filterBy);
        if ($filterBy !== null && $filterBy->hasParam('start') && $filterBy->hasParam('length')) {
            $limit = ' LIMIT ' . intval($filterBy->getInt('start', ['default' => 0])) . ', '
                . $filterBy->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $maxDuration = 0;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $maxDuration = $maxDuration + $this->getSanitizer($row)->getDouble('duration');
        }

        if ($maxDuration > 86400) {
            $postUnits = __('Days');
            $divisor = 86400;
        }
        else if ($maxDuration > 3600) {
            $postUnits = __('Hours');
            $divisor = 3600;
        }
        else {
            $postUnits = __('Minutes');
            $divisor = 60;
        }

        foreach ($this->store->select($sql, $params) as $row) {
            $sanitizedRow = $this->getSanitizer($row);

            $entry = [];
            $entry['displayId'] = $sanitizedRow->getInt(('displayId'));
            $entry['display'] = $sanitizedRow->getString(('display'));
            $entry['timeDisconnected'] =  round($sanitizedRow->getDouble('duration') / $divisor, 2);
            $entry['timeConnected'] =  round($sanitizedRow->getDouble('filter') / $divisor - $entry['timeDisconnected'], 2);
            $entry['postUnits'] = $postUnits;

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select($select . $body, $params);
            $this->getState()->recordsTotal = count($results);
        }

        //
        // Output Results
        // --------------
        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);

        $availabilityData = [];
        $availabilityDataConnected = [];
        $availabilityLabels = [];
        $postUnits = "";
        $dataSets = [];

        foreach ($rows as $row) {
            $availabilityData[] = $row['timeDisconnected'];
            $availabilityDataConnected[] = $row['timeConnected'];
            $availabilityLabels[] = $row['display'];
            $postUnits = $row['postUnits'];
        }

        return [

            'table' => $rows,
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $availabilityLabels,
                    'datasets' => [
                        [
                            'backgroundColor' => 'rgb(11, 98, 164)',
                            'data' => $availabilityData,
                            'label' => __('Downtime')
                        ],
                        [
                            'backgroundColor' => 'rgb(0, 255, 0)',
                            'data' => $availabilityDataConnected,
                            'label' => __('Uptime')
                        ]
                    ]
                ],
                'options' => [

                    'scales' => [
                        'xAxes' => [
                            [
                                'stacked' => true
                            ]
                        ],
                        'yAxes' => [
                            [
                                'stacked' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' =>  $postUnits
                                ]
                            ]
                        ]
                    ],
                    'legend' =>  [
                        'display' => false
                    ],
                    'maintainAspectRatio' => false
                ]
            ],
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),

        ];

    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        return $result;
    }
}