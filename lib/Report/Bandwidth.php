<?php

namespace Xibo\Report;

use Carbon\Carbon;
use Psr\Container\ContainerInterface;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Bandwidth
 * @package Xibo\Report
 */
class Bandwidth implements ReportInterface
{
    use ReportDefaultTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');

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
        return 'bandwidth-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'bandwidth-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'bandwidth-report-form',
            'bandwidth',
            'Display',
            [
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
        $data['reportName'] = 'bandwidth';

        return [
            'template' => 'bandwidth-schedule-form-add',
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

        // Bandwidth report does not support weekly as bandwidth has monthly records in DB
        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';
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
        return sprintf(__('%s bandwidth report', ucfirst($sanitizedParams->getString('filter'))));
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
            $json['recordsTotal'],
            $json['chart'],
            $json['hasChartData']
        );
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
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

        if ($displayId != 0) {
            $SQL .= ', bandwidthtype.name AS type ';
        }

        // For user with limited access, return only data for displays this user has permissions to.
        $joinType = ($this->userFactory->getById($this->getUserId())->isSuperAdmin()) ? 'LEFT OUTER JOIN' : 'INNER JOIN';

        $SQL .= ' FROM `bandwidth` ' .
                $joinType . ' `display`
                ON display.displayid = bandwidth.displayid AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0) {
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';
        }

        $SQL .= '  WHERE month > :month
                AND month < :month2 ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0) {
            $SQL .= ' , bandwidthtype.name ';
        }

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
        $chart = [
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
        ];

        // ----
        // Chart Only
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            [
                'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            ],
            [],
            0,
            $chart,
            count($data) > 0
        );
    }
}
