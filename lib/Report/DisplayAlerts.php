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
use Xibo\Controller\DataTablesDotNetTrait;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class DisplayAlerts
 * @package Xibo\Report
 */
class DisplayAlerts implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

    /** @var DisplayFactory */
    private $displayFactory;
    /** @var DisplayGroupFactory */
    private $displayGroupFactory;
    /** @var DisplayEventFactory */
    private $displayEventFactory;
    
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->displayEventFactory = $container->get('displayEventFactory');

        return $this;
    }

    public function getReportEmailTemplate()
    {
        return 'displayalerts-email-template.twig';
    }

    public function getSavedReportTemplate()
    {
        return 'displayalerts-report-preview';
    }

    public function getReportForm()
    {
        return new ReportForm(
            'displayalerts-report-form',
            'displayalerts',
            'Display',
            [
                'fromDate' => Carbon::now()->startOfMonth()->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ]
        );
    }

    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data = [];
        $data['reportName'] = 'displayalerts';

        return [
            'template' => 'displayalerts-schedule-form-add',
            'data' => $data
        ];
    }

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

    public function generateSavedReportName(SanitizerInterface $sanitizedParams)
    {
        return sprintf(__('%s report for Display'), ucfirst($sanitizedParams->getString('filter')));
    }

    public function restructureSavedReportOldJson($json)
    {
        return $json;
    }

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
        );
    }

    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $displayIds = $this->getDisplayIdFilter($sanitizedParams);
        $onlyLoggedIn = $sanitizedParams->getCheckbox('onlyLoggedIn') == 1;

        //
        // From and To Date Selection
        // --------------------------
        // The report uses a custom range filter that automatically calculates the from/to dates
        // depending on the date range selected.
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');
        $currentDate = Carbon::now()->startOfDay();

        // If toDt is current date then make it current datetime
        if ($toDt->format('Y-m-d') == $currentDate->format('Y-m-d')) {
            $toDt = Carbon::now();
        }

        $metadata = [
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))
                ->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))
                ->format(DateFormatHelper::getSystemFormat()),
        ];

        $params = [
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        ];

        $sql = 'SELECT
            `displayevent`.displayId,
            `display`.display,
            `displayevent`.start,
            `displayevent`.end,
            `displayevent`.eventTypeId,
            `displayevent`.refId,
            `displayevent`.detail 
                FROM `displayevent` 
                INNER JOIN `display` ON `display`.displayId = `displayevent`.displayId
                INNER JOIN `lkdisplaydg` ON `display`.displayId = `lkdisplaydg`.displayId    
                INNER JOIN `displaygroup` ON `displaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                                          AND `displaygroup`.isDisplaySpecific = 1
            WHERE `displayevent`.eventDate BETWEEN :start AND :end   ';

        $eventTypeIdFilter = $sanitizedParams->getString('eventType');

        if ($eventTypeIdFilter != -1) {
            $params['eventTypeId'] = $eventTypeIdFilter;

            $sql .= 'AND `displayevent`.eventTypeId = :eventTypeId ';
        }

        if (count($displayIds) > 0) {
            $sql .= 'AND `displayevent`.displayId IN (' . implode(',', $displayIds) . ')';
        }

        if ($onlyLoggedIn) {
            $sql .= ' AND `display`.loggedIn = 1 ';
        }

        // Tags
        if (!empty($sanitizedParams->getString('tags'))) {
            $tagFilter = $sanitizedParams->getString('tags');

            if (trim($tagFilter) === '--no-tag') {
                $sql .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $sanitizedParams->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';
                $logicalOperator = $sanitizedParams->getString('logicalOperator', ['default' => 'OR']);
                $allTags = explode(',', $tagFilter);
                $notTags = [];
                $tags = [];

                foreach ($allTags as $tag) {
                    if (str_starts_with($tag, '-')) {
                        $notTags[] = ltrim(($tag), '-');
                    } else {
                        $tags[] = $tag;
                    }
                }

                if (!empty($notTags)) {
                    $sql .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                      FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    ';

                    $this->displayFactory->tagFilter(
                        $notTags,
                        'lktagdisplaygroup',
                        'lkTagDisplayGroupId',
                        'displayGroupId',
                        $logicalOperator,
                        $operator,
                        true,
                        $sql,
                        $params
                    );
                }

                if (!empty($tags)) {
                    $sql .= ' AND `displaygroup`.displaygroupId IN (
                                SELECT `lktagdisplaygroup`.displaygroupId
                                  FROM tag
                                    INNER JOIN `lktagdisplaygroup`
                                    ON `lktagdisplaygroup`.tagId = tag.tagId
                                ';

                    $this->displayFactory->tagFilter(
                        $tags,
                        'lktagdisplaygroup',
                        'lkTagDisplayGroupId',
                        'displayGroupId',
                        $logicalOperator,
                        $operator,
                        false,
                        $sql,
                        $params
                    );
                }
            }
        }

        // Sorting?
        $sortOrder = $this->gridRenderSort($sanitizedParams);

        if (is_array($sortOrder)) {
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $rows = [];
        foreach ($this->store->select($sql, $params) as $row) {
            $displayEvent = $this->displayEventFactory->createEmpty()->hydrate($row);
            $displayEvent->setUnmatchedProperty(
                'eventType',
                $displayEvent->getEventNameFromId($displayEvent->eventTypeId)
            );
            $displayEvent->setUnmatchedProperty(
                'display',
                $row['display']
            );

            $rows[] = $displayEvent;
        }

        return new ReportResult(
            $metadata,
            $rows,
            count($rows),
        );
    }
}
