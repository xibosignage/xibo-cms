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
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
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
 * Class LibraryUsage
 * @package Xibo\Report
 */
class LibraryUsage implements ReportInterface
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
     * @var UserGroupFactory
     */
    private $userGroupFactory;

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
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->reportService = $container->get('reportService');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'libraryusage-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(Request $request)
    {
        $title = __('Add Report Schedule');

        $data = [];

        $data['formTitle'] = $title;
        $data['reportName'] = 'libraryusage';
        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();

        return [
            'template' => 'libraryusage-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(Request $request)
    {

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $filter = $sanitizedParams->getString('filter');

        $userId = $sanitizedParams->getInt('userId');
        $filterCriteria['userId'] = $userId;

        $groupId = $sanitizedParams->getInt('groupId');
        $filterCriteria['groupId'] = $groupId;

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
        return sprintf(__('%s library usage report', ucfirst($filterCriteria['filter'])));
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
            'template' => 'libraryusage-report-preview',
            'savedReport' => $savedReport,
            'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)->format(DateFormatHelper::getSystemFormat())
        ]);
    }

    /** @inheritdoc */
    public function getReportForm()
    {

        $data = [];

        // Set up some suffixes
        $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');

        // Widget for the library usage pie chart
        try {
            if ($this->userFactory->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->userFactory->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId');
            $sql .= ' GROUP BY type ';

            $sth = $this->store->getConnection()->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $libraryUsage = [];
            $libraryLabels = [];
            $totalSize = 0;
            foreach ($results as $library) {
                $libraryUsage[] = round((double)$library['SumSize'] / (pow(1024, $base)), 2);
                $libraryLabels[] = ucfirst($library['type']) . ' ' . $suffixes[$base];

                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);

                $libraryUsage[] = $remaining;
                $libraryLabels[] = __('Free') . ' ' . $suffixes[$base];
            }

            // What if we are empty?
            if (count($results) == 0 && $libraryLimit <= 0) {
                $libraryUsage[] = 0;
                $libraryLabels[] = __('Empty');
            }

            $data['libraryLimitSet'] = ($libraryLimit > 0);
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidgetLabels'] = json_encode($libraryLabels);
            $data['libraryWidgetData'] = json_encode($libraryUsage);

        } catch (\Exception $exception) {
            $this->getLog()->error('Error rendering the library stats page widget');
        }

        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();
        $data['availableReports'] = $this->reportService->listReports();

        return [
            'template' => 'libraryusage-report-form',
            'data' =>  $data
        ];
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
                $fromDt= $now;
                $toDt = $now;

                break;
        }


        $params = [];
        $select = '
            SELECT `user`.userId,
                `user`.userName,
                IFNULL(SUM(`media`.FileSize), 0) AS bytesUsed,
                COUNT(`media`.mediaId) AS numFiles
        ';
        $body = '     
              FROM `user`
                LEFT OUTER JOIN `media`
                ON `media`.userID = `user`.UserID
              WHERE 1 = 1
        ';

        // Restrict on the users we have permission to see
        // Normal users can only see themselves
        $permissions = '';
        if ($this->userFactory->getUser()->userTypeId == 3) {
            $permissions .= ' AND user.userId = :currentUserId ';
            $filterBy['currentUserId'] = $this->userFactory->getUser()->userId;
        }
        // Group admins can only see users from their groups.
        else if ($this->userFactory->getUser()->userTypeId == 2) {
            $permissions .= '
                AND user.userId IN (
                    SELECT `otherUserLinks`.userId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                            AND `group`.isUserSpecific = 0
                        INNER JOIN `lkusergroup` `otherUserLinks`
                        ON `otherUserLinks`.groupId = `group`.groupId
                     WHERE `lkusergroup`.userId = :currentUserId
                )
            ';
            $params['currentUserId'] = $this->userFactory->getUser()->userId;
        }

        // Filter by userId
        if ($sanitizedParams->getInt('userId') !== null) {
            $body .= ' AND user.userId = :userId ';
            $params['userId'] = $sanitizedParams->getInt('userId');
        }

        // Filter by groupId
        if ($sanitizedParams->getInt('groupId') !== null) {
            $body .= ' AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupId = :groupId) ';
            $params['groupId'] = $sanitizedParams->getInt('groupId');
        }

        $body .= $permissions;
        $body .= '            
            GROUP BY `user`.userId,
              `user`.userName
        ';

        // Sorting?
        $filterBy = $this->gridRenderFilter($filterCriteria);
        $sortOrder = $this->gridRenderSort($filterCriteria);

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedParams->getInt('start') !== null && $sanitizedParams->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedParams->getInt('start'), 0) . ', ' . $sanitizedParams->getInt('length',['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];
            $sanitizedRow = $this->getSanitizer($row);

            $entry['userId'] = $sanitizedRow->getInt('userId');
            $entry['userName'] = $sanitizedRow->getString('userName');
            $entry['bytesUsed'] = $sanitizedRow->getInt('bytesUsed');
            $entry['bytesUsedFormatted'] = ByteFormatter::format($sanitizedRow->getInt('bytesUsed'), 2);
            $entry['numFiles'] = $sanitizedRow->getInt('numFiles');

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('SELECT COUNT(*) AS total FROM `user` ' . $permissions, $params);
            $this->getState()->recordsTotal = intval($results[0]['total']);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);

        // Get the Library widget labels and Widget Data
        $libraryWidgetLabels = [];
        $libraryWidgetData = [];
        $suffixes = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

        // Widget for the library usage pie chart
        try {
            if ($this->userFactory->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->userFactory->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId');
            $sql .= ' GROUP BY type ';

            $sth = $this->store->getConnection()->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $libraryUsage = [];
            $libraryLabels = [];
            $totalSize = 0;
            foreach ($results as $library) {
                $libraryUsage[] = round((double)$library['SumSize'] / (pow(1024, $base)), 2);
                $libraryLabels[] = ucfirst($library['type']) . ' ' . $suffixes[$base];

                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);

                $libraryUsage[] = $remaining;
                $libraryLabels[] = __('Free') . ' ' . $suffixes[$base];
            }

            // What if we are empty?
            if (count($results) == 0 && $libraryLimit <= 0) {
                $libraryUsage[] = 0;
                $libraryLabels[] = __('Empty');
            }

            $libraryWidgetLabels = $libraryLabels;
            $libraryWidgetData = $libraryUsage;

        } catch (\Exception $exception) {
            $this->getLog()->error('Error rendering the library stats page widget');
        }


        // Build the Library Usage and  User Percentage Usage chart data
        $totalSize = 0;
        foreach ($rows as $row) {
            $totalSize += $row['bytesUsed'];
        }

        $userData = [];
        $userLabels = [];
        foreach ($rows as $row) {
            $userData[] = ($row['bytesUsed']/$totalSize)*100;
            $userLabels[] = $row['userName'];
        }

        $colours = [];
        foreach ($userData as $userDatum) {
            $colours[] = 'rgb(' . mt_rand(0, 255).','. mt_rand(0, 255).',' . mt_rand(0, 255) .')';
        }

        $libraryColours = [];
        foreach ($libraryWidgetData as $libraryDatum) {
            $libraryColours[] = 'rgb(' . mt_rand(0, 255).','. mt_rand(0, 255).',' . mt_rand(0, 255) .')';
        }

        return [
            'chart' => [
                'User_Percentage_Usage' => [ // we will use User_Percentage_Usage as report name when we export/email pdf
                    'type' => 'pie',
                    'data' => [
                        'labels' => $userLabels,
                        'datasets' => [
                            [
                                'backgroundColor' => $colours,
                                'data' => $userData
                            ]
                        ]
                    ],
                    'options' => [
                        'maintainAspectRatio' => false
                    ]
                ],
                'Library_Usage' => [
                    'type' => 'pie',
                    'data' => [
                        'labels' => $libraryWidgetLabels,
                        'datasets' => [
                            [
                                'backgroundColor' => $libraryColours,
                                'data' => $libraryWidgetData
                            ]
                        ]
                    ],
                    'options' => [
                        'maintainAspectRatio' => false
                    ]
                ]
            ],
            'table' => $rows,
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