<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Report;

use Carbon\Carbon;
use Psr\Container\ContainerInterface;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\DateFormatHelper;
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
        $data = [];
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
        $displayIds = $this->getDisplayIdFilter($sanitizedParams);

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
        $joinType = ($this->getUser()->isSuperAdmin()) ? 'LEFT OUTER JOIN' : 'INNER JOIN';

        $SQL .= ' FROM `bandwidth` ' .
                $joinType . ' `display`
                ON display.displayid = bandwidth.displayid    ';


        // Displays
        if (count($displayIds) > 0) {
            $SQL .= ' AND display.displayId IN (' . implode(',', $displayIds) . ') ';
        }

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

        $SQL .= 'GROUP BY display.displayId, display.display ';

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
        // We need to put a fallback value in case it returns an infinite value
        $base = !is_infinite(floor(log($maxSize) / log(1024))) ? floor(log($maxSize) / log(1024)) : 0;

        $labels = [];
        $data = [];
        $backgroundColor = [];

        $rows = [];


        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');
        foreach ($results as $row) {
            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $label = $row['type'];
                $labels[] = $label;
            } else {
                $label = $row['display'] === null ? __('Deleted Displays') : $row['display'];
                $labels[] = $label;
            }
            $backgroundColor[] = ($row['display'] === null) ? 'rgb(255,0,0)' : 'rgb(11, 98, 164)';
            $bandwidth = round((double)$row['size'] / (pow(1024, $base)), 2);
            $data[] = $bandwidth;

            // ----
            // Build Tabular data
            $entry = [];
            $entry['label'] = $label;
            $entry['bandwidth'] = $bandwidth;
            $entry['unit'] = (isset($suffixes[$base]) ? $suffixes[$base] : '');
            $rows[] = $entry;
        }

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

        $metadata =   [
            'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat()),
        ];

        // Total records
        $recordsTotal = count($rows);

        // ----
        // Chart Only
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            $metadata,
            $rows,
            $recordsTotal,
            $chart
        );
    }
}
