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
 * Class Bandwidth
 * @package Xibo\Report
 */
class Bandwidth implements ReportInterface
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
    public function getReportEmailTemplate()
    {
        return 'bandwidth-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'bandwidth-report-form',
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
        $data['reportName'] = 'bandwidth';

        return [
            'template' => 'bandwidth-schedule-form-add',
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

        // Bandwidth report does not support weekly as bandwidth has monthly records in DB
        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';

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
        return sprintf(__('%s bandwidth report', ucfirst($filterCriteria['filter'])));
    }

    /** @inheritdoc */
    public function getReportChartScript($results)
    {
        return json_encode($results['results']['chart']);
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Return data to build chart
        return array_merge($json, [
            'template' => 'bandwidth-report-preview',
            'savedReport' => $savedReport,
            'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)->format(DateFormatHelper::getSystemFormat())
        ]);
    }

    /** @inheritdoc */
    public function getResults($filterCriteria)
    {
        $this->getLog()->debug('Filter criteria: '. json_encode($filterCriteria, JSON_PRETTY_PRINT));

        $sanitizedParams = $this->getSanitizer($filterCriteria);

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

        // Bandwidth report does not support weekly as bandwidth has monthly records in DB
        switch ($reportFilter) {

            // Daily report if setup which has reportfilter = yesterday will be daily progression of bandwidth usage
            // It always starts from the start of the month so we get the month usage
            case 'yesterday':
                $fromDt = $now->copy()->startOfDay()->subDay();
                $fromDt->startOfMonth();

                $toDt = $now->copy()->startOfDay();

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
                $fromDt = $sanitizedParams->getDate('fromDt', ['default' => $sanitizedParams->getDate('bandwidthFromDt')]);
                $fromDt->startOfMonth();

                $toDt = $sanitizedParams->getDate('toDt', ['default' => $sanitizedParams->getDate('bandwidthToDt')]);
                $toDt->addMonth();

                break;
        }


        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query(null, []) as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0) {
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
        }

        // Get some data for a bandwidth chart
        $dbh = $this->store->getConnection();

        $displayId = $sanitizedParams->getInt('displayId');

        $params = [
            'month' => $fromDt->copy()->format('U'),
            'month2' => $toDt->copy()->format('U')
        ];

        $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

        if ($displayId != 0)
            $SQL .= ', bandwidthtype.name AS type ';

        $SQL .= ' FROM `bandwidth`
                LEFT OUTER JOIN `display`
                ON display.displayid = bandwidth.displayid AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0)
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';

        $SQL .= '  WHERE month > :month
                AND month < :month2 ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0)
            $SQL .= ' , bandwidthtype.name ';

        $SQL .= 'ORDER BY display.display';

        $sth = $dbh->prepare($SQL);

        $sth->execute($params);

        // Get the results
        $results = $sth->fetchAll();

        $maxSize = 0;
        foreach ($results as $library) {
            $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
        }

        // Decide what our units are going to be, based on the size
        $base = floor(log($maxSize) / log(1024));

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $labels[] = $row['type'];
            } else {
                $labels[] = $row['display'] === null ? __('Deleted Displays') : $row['display'];
            }
            $backgroundColor[] = ($row['display'] === null) ? 'rgb(255,0,0)' : 'rgb(11, 98, 164)';
            $data[] = round((double)$row['size'] / (pow(1024, $base)), 2);
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        //
        // Output Results
        // --------------

        // Return data to build chart
        return [
            'hasData' => count($data) > 0,
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => __('Bandwidth'),
                            'backgroundColor' => $backgroundColor,
                            'data' => $data
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'yAxes' => [
                            [
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' =>  (isset($suffixes[$base]) ? $suffixes[$base] : '')
                                ]
                            ]
                        ]
                    ],
                    'legend' =>  [
                        'display' => false
                    ],
                    'maintainAspectRatio' => true
                ]
            ],
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),

        ];

    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        return [];
    }
}