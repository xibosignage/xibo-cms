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
use Xibo\Factory\AuditLogFactory;
use Xibo\Factory\LogFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class SessionHistory implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

    /** @var LogFactory */
    private $logFactory;

    /** @var AuditLogFactory */
    private $auditLogFactory;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->logFactory = $container->get('logFactory');
        $this->auditLogFactory = $container->get('auditLogFactory');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'sessionhistory-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'sessionhistory-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm(): ReportForm
    {
        return new ReportForm(
            'sessionhistory-report-form',
            'sessionhistory',
            'Audit',
            [
                'fromDate' => Carbon::now()->startOfMonth()->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ]
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams): array
    {
        $data = [];
        $data['reportName'] = 'sessionhistory';

        return [
            'template' => 'sessionhistory-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams): array
    {
        $filter = $sanitizedParams->getString('filter');
        $filterCriteria['userId'] = $sanitizedParams->getInt('userId');
        $filterCriteria['type'] = $sanitizedParams->getString('type');
        $filterCriteria['scheduledReport'] = true;

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

    public function generateSavedReportName(SanitizerInterface $sanitizedParams): string
    {
        return sprintf(
            __('%s Session %s log report for User'),
            ucfirst($sanitizedParams->getString('filter')),
            ucfirst($sanitizedParams->getString('type'))
        );
    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($json)
    {
        return $json;
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
        );
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $currentDate = Carbon::now()->startOfDay();

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

        $type = $sanitizedParams->getString('type');

        if ($type === 'audit') {
            $params = [
                'fromDt' => $fromDt->format('U'),
                'toDt' => $toDt->format('U'),
            ];

            $sql = 'SELECT
             `auditlog`.`logId`,
             `auditlog`.`logDate`,
             `user`.`userName`,
             `auditlog`.`message`,
             `auditlog`.`objectAfter`,
             `auditlog`.`entity`,
             `auditlog`.`entityId`,
             `auditlog`.userId,
             `auditlog`.ipAddress,
             `auditlog`.sessionHistoryId,
             `session_history`.userAgent
                FROM `auditlog`
                    INNER JOIN `user` ON `user`.`userId` = `auditlog`.`userId`
                    INNER JOIN `session_history` ON `session_history`.`sessionId` = `auditlog`.`sessionHistoryId`
             WHERE `auditlog`.logDate BETWEEN :fromDt AND :toDt
             ';

            if ($sanitizedParams->getInt('userId') !== null) {
                $sql .= ' AND `auditlog`.`userId` = :userId';
                $params['userId'] = $sanitizedParams->getInt('userId');
            }

            if ($sanitizedParams->getInt('sessionHistoryId') !== null) {
                $sql .= ' AND `auditlog`.`sessionHistoryId` = :sessionHistoryId';
                $params['sessionHistoryId'] = $sanitizedParams->getInt('sessionHistoryId');
            }

            // Sorting?
            $sortOrder = $this->gridRenderSort($sanitizedParams);

            if (is_array($sortOrder)) {
                $sql .= ' ORDER BY ' . implode(',', $sortOrder);
            }

            $rows = [];
            foreach ($this->store->select($sql, $params) as $row) {
                $auditRecord = $this->auditLogFactory->create()->hydrate($row);
                $auditRecord->setUnmatchedProperty(
                    'userAgent',
                    $row['userAgent']
                );

                // decode for grid view, leave as json for email/preview.
                if (!$sanitizedParams->getCheckbox('scheduledReport')) {
                    $auditRecord->objectAfter = json_decode($auditRecord->objectAfter);
                }

                $auditRecord->logDate = Carbon::createFromTimestamp($auditRecord->logDate)
                    ->format(DateFormatHelper::getSystemFormat());

                $rows[] = $auditRecord;
            }

            return new ReportResult(
                $metadata,
                $rows,
                count($rows),
            );
        } else if ($type === 'debug') {
            $params = [
                'fromDt' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'toDt' => $toDt->format(DateFormatHelper::getSystemFormat()),
            ];

            $sql = 'SELECT
             `log`.`logId`,
             `log`.`logDate`,
             `log`.`runNo`,
             `log`.`channel`,
             `log`.`page`,
             `log`.`function`,
             `log`.`type`,
             `log`.`message`,
             `log`.`userId`,
             `log`.`sessionHistoryId`,
             `user`.`userName`,
             `display`.`displayId`,
             `display`.`display`,
             `session_history`.ipAddress,
             `session_history`.userAgent
                FROM `log`
                    LEFT OUTER JOIN `display` ON `display`.`displayid` = `log`.`displayid`
                    INNER JOIN `user` ON `user`.`userId` = `log`.`userId`
                    INNER JOIN `session_history` ON `session_history`.`sessionId` = `log`.`sessionHistoryId`
             WHERE `log`.logDate BETWEEN :fromDt AND :toDt
             ';

            if ($sanitizedParams->getInt('userId') !== null) {
                $sql .= ' AND `log`.`userId` = :userId';
                $params['userId'] = $sanitizedParams->getInt('userId');
            }

            if ($sanitizedParams->getInt('sessionHistoryId') !== null) {
                $sql .= ' AND `log`.`sessionHistoryId` = :sessionHistoryId';
                $params['sessionHistoryId'] = $sanitizedParams->getInt('sessionHistoryId');
            }

            // Sorting?
            $sortOrder = $this->gridRenderSort($sanitizedParams);

            if (is_array($sortOrder)) {
                $sql .= ' ORDER BY ' . implode(',', $sortOrder);
            }

            $rows = [];
            foreach ($this->store->select($sql, $params) as $row) {
                $logRecord = $this->logFactory->createEmpty()->hydrate($row, ['htmlStringProperties' => ['message']]);
                $logRecord->setUnmatchedProperty(
                    'userAgent',
                    $row['userAgent']
                );

                $logRecord->setUnmatchedProperty(
                    'ipAddress',
                    $row['ipAddress']
                );

                $logRecord->setUnmatchedProperty(
                    'userName',
                    $row['userName']
                );

                $rows[] = $logRecord;
            }

            return new ReportResult(
                $metadata,
                $rows,
                count($rows),
            );
        } else {
            $params = [
                'fromDt' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'toDt' => $toDt->format(DateFormatHelper::getSystemFormat()),
            ];

            $sql = 'SELECT
             `session_history`.`sessionId`,
             `session_history`.`startTime`,
             `session_history`.`userId`,
             `session_history`.`userAgent`,
             `session_history`.`ipAddress`,
             `session_history`.`lastUsedTime`,
             `user`.`userName`,
             `usertype`.`userType`
                FROM `session_history`
                    LEFT OUTER JOIN `user` ON `user`.`userId` = `session_history`.`userId`
                    LEFT OUTER JOIN `usertype` ON `usertype`.`userTypeId` = `user`.`userTypeId`
             WHERE `session_history`.startTime BETWEEN :fromDt AND :toDt
             ';

            if ($sanitizedParams->getInt('userId') !== null) {
                $sql .= ' AND `session_history`.`userId` = :userId';
                $params['userId'] = $sanitizedParams->getInt('userId');
            }

            if ($sanitizedParams->getInt('sessionHistoryId') !== null) {
                $sql .= ' AND `session_history`.`sessionId` = :sessionHistoryId';
                $params['sessionHistoryId'] = $sanitizedParams->getInt('sessionHistoryId');
            }

            // Sorting?
            $sortOrder = $this->gridRenderSort($sanitizedParams);

            if (is_array($sortOrder)) {
                $sql .= ' ORDER BY ' . implode(',', $sortOrder);
            }

            $rows = [];
            foreach ($this->store->select($sql, $params) as $row) {
                $sessionRecord = $this->logFactory->createEmpty()->hydrate($row, ['htmlStringProperties' => ['message']]);
                $duration = isset($row['lastUsedTime'])
                    ? date_diff(date_create($row['startTime']), date_create($row['lastUsedTime']))->format('%H:%I:%S')
                    : null;

                $sessionRecord->setUnmatchedProperty(
                    'userAgent',
                    $row['userAgent']
                );

                $sessionRecord->setUnmatchedProperty(
                    'ipAddress',
                    $row['ipAddress']
                );

                $sessionRecord->setUnmatchedProperty(
                    'userName',
                    $row['userName']
                );

                $sessionRecord->setUnmatchedProperty(
                    'endTime',
                    $row['lastUsedTime']
                );

                $sessionRecord->setUnmatchedProperty(
                    'duration',
                    $duration
                );

                $rows[] = $sessionRecord;
            }

            return new ReportResult(
                $metadata,
                $rows,
                count($rows),
            );
        }
    }
}
