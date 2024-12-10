<?php
/*
 * Copyright (C) 2022-2024 Xibo Signage Ltd
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
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class TimeConnected
 * @package Xibo\Report
 */
class TimeConnected implements ReportInterface
{
    use ReportDefaultTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->reportService = $container->get('reportService');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'timeconnected-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'timeconnected-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        $groups = [];
        $displays = [];

        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        return new ReportForm(
            'timeconnected-report-form',
            'timeconnected',
            'Display',
            [
                'displays' => $displays,
                'displayGroups' => $groups,
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ],
            __('Select a type and an item (i.e., layout/media/tag)')
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data['hiddenFields'] = '{}';
        $data['reportName'] = 'timeconnected';

        $groups = [];
        $displays = [];
        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }
        $data['displays'] = $displays;
        $data['displayGroups'] = $groups;

        return [
            'template' => 'timeconnected-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $groupByFilter = $sanitizedParams->getString('groupByFilter');
        $displayGroupIds = $sanitizedParams->getIntArray('displayGroupIds');
        $hiddenFields = json_decode($sanitizedParams->getString('hiddenFields'), true);

        $filterCriteria['displayGroupIds'] = $displayGroupIds;
        $filterCriteria['filter'] = $filter;

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupByFilter'] = $groupByFilter;
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
        return sprintf(__('%s report for Display', ucfirst($sanitizedParams->getString('filter'))));
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
        // Get an array of display id this user has access to.
        $displayIds = $this->getDisplayIdFilter($sanitizedParams);

        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determines whether the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.

        // Range
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Expect dates to be provided.
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');

        // Use the group by filter provided
        // NB: this differs from the Summary Report where we set the group by according to the range selected
        $groupByFilter = $sanitizedParams->getString('groupByFilter');

        //
        // Get Results!
        // with keys "result", "periods", "periodStart", "periodEnd"
        // -------------
        $result = $this->getTimeDisconnectedMySql($fromDt, $toDt, $groupByFilter, $displayIds);

        //
        // Output Results
        // --------------
        if ($this->getUser()->isSuperAdmin()) {
            $sql = 'SELECT displayId, display FROM display WHERE 1 = 1';
            if (count($displayIds) > 0) {
                $sql .= ' AND displayId IN (' . implode(',', $displayIds) . ')';
            }
        }

        $timeConnected = [];
        $displays = [];
        $i = 0;
        $key = 0;
        foreach ($this->store->select($sql, []) as $row) {
            $displayId = intval($row['displayId']);
            $displayName = $row['display'];

            // Set the display name for the displays in this row.
            $displays[$key][$displayId] = $displayName;

            // Go through each period
            foreach ($result['periods'] as $resPeriods) {
                //
                $temp = $resPeriods['customLabel'];
                if (empty($timeConnected[$temp][$displayId]['percent'])) {
                    $timeConnected[$key][$temp][$displayId]['percent'] = 100;
                }
                if (empty($timeConnected[$temp][$displayId]['label'])) {
                    $timeConnected[$key][$temp][$displayId]['label'] = $resPeriods['customLabel'];
                }

                foreach ($result['result'] as $res) {
                    if ($res['displayId'] == $displayId && $res['customLabel'] == $resPeriods['customLabel']) {
                        $timeConnected[$key][$temp][$displayId]['percent'] =  100 - round($res['percent'], 2);
                        $timeConnected[$key][$temp][$displayId]['label'] = $resPeriods['customLabel'];
                    } else {
                        continue;
                    }
                }
            }

            $i++;
            if ($i >= 3) {
                $i = 0;
                $key++;
            }
        }

        // ----
        // No grid
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            [
                'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            ],
            [
                'timeConnected' => $timeConnected,
                'displays' => $displays
            ]
        );
    }

    /**
     * MySQL distribution report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, bydayofweek and bydayofmonth
     * @param array $displayIds
     * @return array
     */
    private function getTimeDisconnectedMySql($fromDt, $toDt, $groupByFilter, $displayIds)
    {

        if ($groupByFilter == 'bydayofmonth') {
            $customLabel = 'Y-m-d (D)';
        } else {
            $customLabel = 'Y-m-d g:i A';
        }

        // Create periods covering the from/to dates
        // -----------------------------------------
        try {
            $periods = $this->getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter, 'temp_periods', $customLabel);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [];
        }
        try {
            $periods2 = $this->getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter, 'temp_periods2', $customLabel);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [];
        }

        // Join in stats
        // -------------
        $query = '
            SELECT periods.id,               
               periods.start,
               periods.end,
               periods.label,
               periods.customLabel,
               display,
               displayId,
               SUM(duration) AS downtime,
               periods.end - periods.start AS periodDuration,
               SUM(duration) / (periods.end - periods.start) * 100 AS percent
          FROM ' . $periods2 . ' AS periods
            INNER JOIN (
                SELECT id,
                    label,
                    customLabel,
                    display,
                    displayId,
                    GREATEST(periods.start, down.start) AS actualStart,
                    LEAST(periods.end, down.end) AS actualEnd,
                    LEAST(periods.end, down.end) - GREATEST(periods.start, down.start) AS duration,
                    periods.end - periods.start AS periodDuration,
                    (LEAST(periods.end, down.end) - GREATEST(periods.start, down.start)) / (periods.end - periods.start) * 100 AS percent
                  FROM ' . $periods . ' AS periods
                    LEFT OUTER JOIN (
                        SELECT start,
                            IFNULL(end, UNIX_TIMESTAMP()) AS end,
                            displayevent.displayId,
                            display.display
                          FROM displayevent
                            INNER JOIN display
                            ON display.displayId = displayevent.displayId
                          WHERE `displayevent`.eventTypeId = 1 
            ';

        // Displays
        if (count($displayIds) > 0) {
            $query .= ' AND display.displayID IN (' . implode(',', $displayIds) . ') ';
        }

        $query .= '
                    ) down
                    ON down.start < periods.`end`
                        AND down.end > periods.`start`
            ) joined
            ON joined.customLabel = periods.customLabel
        GROUP BY periods.id,
             periods.start,
             periods.end,
             periods.label,
             periods.customLabel,
             joined.display,
             joined.displayId
        ORDER BY id, display
            ';

        return [
            'result' => $this->getStore()->select($query, []),
            'periods' => $this->getStore()->select('SELECT * from ' . $periods, []),
            'periodStart' => $fromDt->format('Y-m-d H:i:s'),
            'periodEnd' => $toDt->format('Y-m-d H:i:s')
        ];
    }
}
