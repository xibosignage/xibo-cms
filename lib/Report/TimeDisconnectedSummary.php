<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
        $data = [];
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
        $displayGroupIds = $sanitizedParams->getIntArray('displayGroupId', ['default' => []]);

        $filterCriteria['displayId'] = $displayId;
        if (empty($displayId) && count($displayGroupIds) > 0) {
            $filterCriteria['displayGroupId'] = $displayGroupIds;
        }
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
        // Filter by displayId?
        $displayIds = $this->getDisplayIdFilter($sanitizedParams);

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

        // Get an array of display groups this user has access to
        $displayGroupIds = [];

        foreach ($this->displayGroupFactory->query(null, [
            'isDisplaySpecific' => -1,
            'userCheckUserId' => $this->getUser()->userId
        ]) as $displayGroup) {
            $displayGroupIds[] = $displayGroup->displayGroupId;
        }

        if (count($displayGroupIds) <= 0) {
            throw new InvalidArgumentException(__('No display groups with View permissions'), 'displayGroup');
        }

        $params = array(
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        );

        // Disconnected Displays Query
        $select = '
            SELECT display.display, display.displayId,
            SUM(LEAST(IFNULL(`end`, :end), :end) - GREATEST(`start`, :start)) AS duration,
            :end - :start as filter ';

        $body = 'FROM `displayevent`
                INNER JOIN `display`
                ON display.displayId = `displayevent`.displayId 
                WHERE `start` <= :end
                  AND IFNULL(`end`, :end) >= :start
                  AND :end <= UNIX_TIMESTAMP(NOW()) ';

        if (count($displayIds) > 0) {
            $body .= 'AND display.displayId IN (' . implode(',', $displayIds) . ') ';
        }

        if ($onlyLoggedIn) {
            $body .= ' AND `display`.loggedIn = 1 ';
        }

        $body .= '
            GROUP BY display.display, display.displayId
        ';

        $sql = $select . $body;
        $maxDuration = 0;

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

        // Tabular Data
        $disconnectedDisplays = [];
        foreach ($this->store->select($sql, $params) as $row) {
            $sanitizedRow = $this->sanitizer->getSanitizer($row);

            $entry = [];
            $entry['timeDisconnected'] =  round($sanitizedRow->getDouble('duration') / $divisor, 2);
            $entry['timeConnected'] =  round($sanitizedRow->getDouble('filter') / $divisor - $entry['timeDisconnected'], 2);
            $disconnectedDisplays[$sanitizedRow->getInt(('displayId'))] = $entry;
        }

        // Displays with filters such as tags
        $displaySelect = '
            SELECT display.display, display.displayId ';

        if ($tags != '') {
            $displaySelect .= ', (SELECT GROUP_CONCAT(DISTINCT tag)
              FROM tag
                INNER JOIN lktagdisplaygroup
                  ON lktagdisplaygroup.tagId = tag.tagId
                WHERE lktagdisplaygroup.displayGroupId = displaygroup.DisplayGroupID
                GROUP BY lktagdisplaygroup.displayGroupId) AS tags ';
        }

        $displayBody = 'FROM `display` ';

        if ($tags != '') {
            $displayBody .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid
                     INNER JOIN `displaygroup`
                        ON displaygroup.displaygroupId = lkdisplaydg.displaygroupId
                         AND `displaygroup`.isDisplaySpecific = 1 ';
        }
        $displayBody .= 'WHERE 1 = 1 ';

        if (count($displayIds) > 0) {
            $displayBody .= 'AND display.displayId IN (' . implode(',', $displayIds) . ') ';
        }

        if ($tags != '') {
            if (trim($tags) === '--no-tag') {
                $displayBody .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $sanitizedParams->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $displayBody .= ' AND `displaygroup`.displaygroupId IN (
                SELECT `lktagdisplaygroup`.displaygroupId
                  FROM tag
                    INNER JOIN `lktagdisplaygroup`
                    ON `lktagdisplaygroup`.tagId = tag.tagId
                ';
                $i = 0;

                foreach (explode(',', $tags) as $tag) {
                    $i++;

                    if ($i == 1) {
                        $displayBody .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    } else {
                        $displayBody .= ' OR `tag` ' . $operator . ' :tags' . $i;
                    }

                    if ($operator === '=') {
                        $params['tags' . $i] = $tag;
                    } else {
                        $params['tags' . $i] = '%' . $tag . '%';
                    }
                }

                $displayBody .= ' ) ';
            }
        }

        if ($onlyLoggedIn) {
            $displayBody .= ' AND `display`.loggedIn = 1 ';
        }

        $displayBody .= '
            GROUP BY display.display, display.displayId
        ';

        // Sorting?
        $sortOrder = $this->gridRenderSort($sanitizedParams);

        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        // Get a list of displays by filters
        $displaySql = $displaySelect . $displayBody . $order;
        $rows = [];

        // Retrieve the disconnected/connected time from the $disconnectedDisplays array into displays
        foreach ($this->store->select($displaySql, $params) as $displayRow) {
            $sanitizedDisplayRow = $this->sanitizer->getSanitizer($displayRow);
            $entry = [];
            $displayId = $sanitizedDisplayRow->getInt(('displayId'));
            $entry['displayId'] = $displayId;
            $entry['display'] = $sanitizedDisplayRow->getString(('display'));
            $entry['timeDisconnected'] = $disconnectedDisplays[$displayId]['timeDisconnected'] ?? 0 ;
            $entry['timeConnected'] = $disconnectedDisplays[$displayId]['timeConnected'] ?? ($toDt->format('U') - $fromDt->format('U')) / $divisor;
            $entry['postUnits'] = $postUnits;
            $rows[] = $entry;
        }

        //
        // Output Results
        // --------------

        $availabilityData = [];
        $availabilityDataConnected = [];
        $availabilityLabels = [];
        $postUnits = '';

        foreach ($rows as $row) {
            $availabilityData[] = $row['timeDisconnected'];
            $availabilityDataConnected[] = $row['timeConnected'];
            $availabilityLabels[] = $row['display'];
            $postUnits = $row['postUnits'];
        }

        // Build Chart to pass in twig file chart.js
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

        $metadata = [
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
        ];

        // ----
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            $metadata,
            $rows,
            count($rows),
            $chart
        );
    }
}
