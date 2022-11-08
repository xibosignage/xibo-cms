<?php

namespace Xibo\Report;

use Carbon\Carbon;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Controller\DataTablesDotNetTrait;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class LibraryUsage
 * @package Xibo\Report
 */
class LibraryUsage implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

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
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var SanitizerService
     */
    private $sanitizer;

    /**
     * @var ApplicationState
     */
    private $state;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        $this->userFactory = $container->get('userFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->reportService = $container->get('reportService');
        $this->configService = $container->get('configService');
        $this->sanitizer = $container->get('sanitizerService');
        $this->dispatcher = $container->get('dispatcher');

        return $this;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /** @inheritdoc */
    public function getReportChartScript($results)
    {
        return json_encode($results->chart);
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'libraryusage-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'libraryusage-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        $data = [];

        // Set up some suffixes
        $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');

        // Widget for the library usage pie chart
        try {
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->configService->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql(
                'Xibo\Entity\Media',
                $sql,
                $params,
                '`media`.mediaId',
                '`media`.userId',
                [
                    'userCheckUserId' => $this->getUser()->userId
                ]
            );
            $sql .= ' GROUP BY type ';

            $sth = $this->store->getConnection()->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();
            // add any dependencies fonts, player software etc to the results
            $event = new \Xibo\Event\DependencyFileSizeEvent($results);
            $this->getDispatcher()->dispatch($event, $event::$NAME);
            $results = $event->getResults();

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

        // Note: getReportForm is only run by the web UI and therefore the logged-in users permissions are checked here
        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();
        $data['availableReports'] = $this->reportService->listReports();

        return new ReportForm(
            'libraryusage-report-form',
            'libraryusage',
            'Library',
            $data
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data = [];
        $data['reportName'] = 'libraryusage';
        
        // Note: getReportScheduleFormData is only run by the web UI and therefore the logged-in users permissions
        // are checked here
        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();

        return [
            'template' => 'libraryusage-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
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
        } elseif ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
        } elseif ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
        } elseif ($filter == 'yearly') {
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
    public function generateSavedReportName(SanitizerInterface $sanitizedParams)
    {
        return sprintf(__('%s library usage report', ucfirst($sanitizedParams->getString('filter'))));
    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        return $result;
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

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $filter = [
            'userId' => $sanitizedParams->getInt('userId'),
            'groupId' => $sanitizedParams->getInt('groupId'),
            'start' => $sanitizedParams->getInt('start'),
            'length' => $sanitizedParams->getInt('length'),
        ];

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
        if ($this->getUser()->userTypeId == 3) {
            $permissions .= ' AND user.userId = :currentUserId ';
            $filterBy['currentUserId'] = $this->getUser()->userId;
        } elseif ($this->getUser()->userTypeId == 2) {
            // Group admins can only see users from their groups.
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
            $params['currentUserId'] = $this->getUser()->userId;
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
        $filterBy = $this->gridRenderFilter($filter);
        $sortOrder = $this->gridRenderSort($sanitizedParams);

        $order = '';
        if (is_array($sortOrder)) {
            $newSortOrder = [];
            foreach ($sortOrder as $sort) {
                if ($sort == '`bytesUsedFormatted`') {
                    $newSortOrder[] = '`bytesUsed`';
                    continue;
                }

                if ($sort == '`bytesUsedFormatted` DESC') {
                    $newSortOrder[] = '`bytesUsed` DESC';
                    continue;
                }
                $newSortOrder[] = $sort;
            }
            $sortOrder = $newSortOrder;

            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null
            && $sanitizedParams->getInt('start') !== null
            && $sanitizedParams->getInt('length') !== null
        ) {
            $limit = ' LIMIT ' . $sanitizedParams->getInt('start', ['default' => 0])
                . ', ' . $sanitizedParams->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];
            $sanitizedRow = $this->sanitizer->getSanitizer($row);

            $entry['userId'] = $sanitizedRow->getInt('userId');
            $entry['userName'] = $sanitizedRow->getString('userName');
            $entry['bytesUsed'] = $sanitizedRow->getInt('bytesUsed');
            $entry['bytesUsedFormatted'] = ByteFormatter::format($sanitizedRow->getInt('bytesUsed'), 2);
            $entry['numFiles'] = $sanitizedRow->getInt('numFiles');

            $rows[] = $entry;
        }

        // Paging
        $recordsTotal = 0;
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('SELECT COUNT(*) AS total FROM `user` ' . $permissions, $params);
            $recordsTotal = intval($results[0]['total']);
        }

        // Get the Library widget labels and Widget Data
        $libraryWidgetLabels = [];
        $libraryWidgetData = [];
        $suffixes = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

        // Widget for the library usage pie chart
        try {
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->userFactory->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->configService->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql(
                'Xibo\Entity\Media',
                $sql,
                $params,
                '`media`.mediaId',
                '`media`.userId',
                [
                    'userCheckUserId' => $this->getUser()->userId
                ]
            );
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

        $chart = [
            // we will use User_Percentage_Usage as report name when we export/email pdf
            'User_Percentage_Usage' => [
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
        ];

        // ----
        // Both Chart and Table
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            [
                'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat()),
            ],
            $rows,
            $recordsTotal,
            $chart
        );
    }
}
