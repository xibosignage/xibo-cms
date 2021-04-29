<?php

namespace Xibo\Report;

use Carbon\Carbon;
use Psr\Container\ContainerInterface;
use Xibo\Controller\DataTablesDotNetTrait;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class TimeDisconnectedSummary
 * @package Xibo\Report
 */
class TimeDisconnectedSummary implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var SanitizerService
     */
    private $sanitizer;

    /**
     * @var ApplicationState
     */
    private $state;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->sanitizer = $container->get('sanitizerService');
        $this->state = $container->get('state');

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
        return 'timedisconnectedsummary-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'timedisconnectedsummary-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'timedisconnectedsummary-report-form',
            'timedisconnectedsummary',
            'Display',
            [
                'fromDate' => Carbon::now()->subSeconds(86400 * 35)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ]
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
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
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $displayId = $sanitizedParams->getInt('displayId');

        $filterCriteria['displayId'] = $displayId;
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
        return sprintf(__('%s time disconnected summary report', ucfirst($sanitizedParams->getString('filter'))));
    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        return $result;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Report result object
        return new ReportResult(
            [
                'periodStart' => $json['metadata']['periodStart'],
                'periodEnd' => $json['metadata']['periodEnd'],
                'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)
                    ->format(DateFormatHelper::getSystemFormat()),
                'title' => $savedReport->saveAs,
            ],
            $json['table'],
            0,
            $json['chart']
        );
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $filter = [
            'displayId' => $sanitizedParams->getInt('displayId'),
            'displayGroupId' => $sanitizedParams->getInt('displayGroupId'),
            'tags' => $sanitizedParams->getString('tags'),
            'onlyLoggedIn' => $sanitizedParams->getCheckbox('onlyLoggedIn') == 1,
            'exactTags' => $sanitizedParams->getCheckbox('exactTags')
        ];

        $displayId = $sanitizedParams->getInt('displayId');
        $displayGroupId = $sanitizedParams->getInt('displayGroupId');
        $tags = $sanitizedParams->getString('tags');
        $onlyLoggedIn = $sanitizedParams->getCheckbox('onlyLoggedIn') == 1;

        $currentDate = Carbon::now()->startOfDay();

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
                $fromDt = $sanitizedParams->getDate('fromDt', ['default' => $sanitizedParams->getDate('availabilityFromDt')]);
                $toDt = $sanitizedParams->getDate('toDt', ['default' => $sanitizedParams->getDate('availabilityToDt')]);

                $fromDt = $fromDt->startOfDay();

                // If toDt is current date then make it current datetime
                if ($toDt->format('Y-m-d') == $currentDate->format('Y-m-d')) {
                    $toDt = Carbon::now();
                } else {
                    $toDt = $toDt->addDay()->startOfDay();
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

                    if ($i == 1) {
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    } else {
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;
                    }

                    if ($operator === '=') {
                        $params['tags' . $i] = $tag;
                    } else {
                        $params['tags' . $i] = '%' . $tag . '%';
                    }
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
        $filterBy = $this->gridRenderFilter($filter);
        $sortOrder = $this->gridRenderSort($sanitizedParams);

        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';

        // Paging
        $filterBy = $this->sanitizer->getSanitizer($filterBy);
        if ($filterBy !== null && $filterBy->hasParam('start') && $filterBy->hasParam('length')) {
            $limit = ' LIMIT ' . intval($filterBy->getInt('start', ['default' => 0])) . ', '
                . $filterBy->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $maxDuration = 0;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $maxDuration = $maxDuration + $this->sanitizer->getSanitizer($row)->getDouble('duration');
        }

        if ($maxDuration > 86400) {
            $postUnits = __('Days');
            $divisor = 86400;
        } elseif ($maxDuration > 3600) {
            $postUnits = __('Hours');
            $divisor = 3600;
        } else {
            $postUnits = __('Minutes');
            $divisor = 60;
        }

        foreach ($this->store->select($sql, $params) as $row) {
            $sanitizedRow = $this->sanitizer->getSanitizer($row);

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
            $this->state->recordsTotal = count($results);
        }

        //
        // Output Results
        // --------------
        $this->state->template = 'grid';
        $this->state->setData($rows);

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

        $chart = [
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
        ];

        // Return data to build chart
        return new ReportResult(
            [
                'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            ],
            $rows,
            0,
            $chart
        );
    }
}
