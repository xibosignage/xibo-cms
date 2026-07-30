<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

    private readonly LogFactory $logFactory;
    private readonly AuditLogFactory $auditLogFactory;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->logFactory = $container->get('logFactory');
        $this->auditLogFactory = $container->get('auditLogFactory');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate(): string
    {
        return 'sessionhistory-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportForm(): ReportForm
    {
        return new ReportForm(
            'sessionhistory',
            'Audit',
            [
                'fromDate' => Carbon::now()->startOfMonth()->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ]
        );
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
    public function restructureSavedReportOldJson($json): array
    {
        return $json;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport): ReportResult
    {
        $metadata = [
            'periodStart' => $json['metadata']['periodStart'],
            'periodEnd' => $json['metadata']['periodEnd'],
            'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)
                ->format(DateFormatHelper::getSystemFormat()),
            'title' => $savedReport->saveAs,
            'type' => $json['metadata']['type'] ?? '',
        ];

        // Report result object
        return new ReportResult(
            $metadata,
            $json['table'],
            $json['recordsTotal'],
        );
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams, bool $isJson = false): ReportResult
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        //
        // From and To Date Selection
        // --------------------------
        // The report uses a custom range filter that automatically calculates the from/to dates
        // depending on the date range selected.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

        // This calculation will be retained as it is used for scheduled reports
        switch ($reportFilter) {
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
                // fromDt will always be from start of day ie 00:00
                $fromDt = $sanitizedParams->getDate('fromDt') ?? $now->copy()->startOfDay();
                $toDt = $sanitizedParams->getDate('toDt') ?? $now;

                break;
        }

        $type = $sanitizedParams->getString('type');

        $metadata = [
            'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat()),
            'type' => $type,
        ];

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
                    LEFT OUTER JOIN `user` ON `user`.`userId` = `auditlog`.`userId`
                    LEFT OUTER JOIN `session_history` ON `session_history`.`sessionId` = `auditlog`.`sessionHistoryId`
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

            $keyword = $sanitizedParams->getString('keyword');
            if ($keyword !== null) {
                $sql .= ' AND (`auditlog`.`message` LIKE :keyword
                          OR `auditlog`.`entity` LIKE :keyword
                          OR `user`.`userName` LIKE :keyword
                          OR `session_history`.`userAgent` LIKE :keyword)';
                $params['keyword'] = '%' . $keyword . '%';
            }

            $sql .= $this->buildOrderBy('audit', $sanitizedParams, $isJson);

            $rows = [];
            foreach ($this->store->select($sql, $params) as $row) {
                $auditRecord = $this->auditLogFactory->create()->hydrate($row);
                $auditRecord->setUnmatchedProperty('userAgent', $row['userAgent']);

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
        } elseif ($type === 'debug') {
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
                    LEFT OUTER JOIN `user` ON `user`.`userId` = `log`.`userId`
                    LEFT OUTER JOIN `session_history` ON `session_history`.`sessionId` = `log`.`sessionHistoryId`
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

            $keyword = $sanitizedParams->getString('keyword');
            if ($keyword !== null) {
                $sql .= ' AND (`log`.`message` LIKE :keyword
                          OR `log`.`page` LIKE :keyword
                          OR `user`.`userName` LIKE :keyword
                          OR `session_history`.`userAgent` LIKE :keyword)';
                $params['keyword'] = '%' . $keyword . '%';
            }

            $sql .= $this->buildOrderBy('debug', $sanitizedParams, $isJson);

            $rows = [];
            foreach ($this->store->select($sql, $params) as $row) {
                $logRecord = $this->logFactory->createEmpty()->hydrate($row, ['htmlStringProperties' => ['message']]);
                $logRecord->setUnmatchedProperty('userAgent', $row['userAgent']);
                $logRecord->setUnmatchedProperty('ipAddress', $row['ipAddress']);
                $logRecord->setUnmatchedProperty('userName', $row['userName']);

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

            $keyword = $sanitizedParams->getString('keyword');
            if ($keyword !== null) {
                $sql .= ' AND (`session_history`.`userAgent` LIKE :keyword
                          OR `session_history`.`ipAddress` LIKE :keyword
                          OR `user`.`userName` LIKE :keyword)';
                $params['keyword'] = '%' . $keyword . '%';
            }

            $sql .= $this->buildOrderBy('sessions', $sanitizedParams, $isJson);

            $rows = [];
            foreach ($this->store->select($sql, $params) as $row) {
                $sessionRecord = $this->logFactory->createEmpty()
                    ->hydrate($row, ['htmlStringProperties' => ['message']]);
                $duration = isset($row['lastUsedTime'])
                    ? date_diff(date_create($row['startTime']), date_create($row['lastUsedTime']))->format('%H:%I:%S')
                    : null;

                $sessionRecord->setUnmatchedProperty('userAgent', $row['userAgent']);
                $sessionRecord->setUnmatchedProperty('ipAddress', $row['ipAddress']);
                $sessionRecord->setUnmatchedProperty('userName', $row['userName']);
                $sessionRecord->setUnmatchedProperty('endTime', $row['lastUsedTime']);
                $sessionRecord->setUnmatchedProperty('duration', $duration);

                $rows[] = $sessionRecord;
            }

            return new ReportResult(
                $metadata,
                $rows,
                count($rows),
            );
        }
    }

    private function buildOrderBy(string $type, SanitizerInterface $sanitizedParams, bool $isJson): string
    {
        [$defaultSortBy, $allowedColumns, $defaultSort] = match ($type) {
            'audit' => [
                'logDate',
                [
                    'logId', 'logDate', 'userName', 'message', 'entity', 'entityId',
                    'userId', 'ipAddress', 'sessionHistoryId', 'userAgent',
                ],
                ['logDate DESC'],
            ],
            'debug' => [
                'logDate',
                [
                    'logId', 'logDate', 'runNo', 'channel', 'page', 'function', 'type',
                    'message', 'userId', 'sessionHistoryId', 'userName',
                    'displayId', 'display', 'ipAddress', 'userAgent',
                ],
                ['logDate DESC'],
            ],
            default => [
                'startTime',
                ['sessionId', 'startTime', 'userId', 'userAgent', 'ipAddress', 'lastUsedTime', 'userName', 'userType'],
                ['startTime DESC'],
            ],
        };

        $sortOrder = $this->gridRenderSort($sanitizedParams, $isJson, $defaultSortBy);
        $order = $this->logFactory->buildSortQuery($sortOrder, $allowedColumns, defaultSort: $defaultSort);

        return !empty($order) ? ' ORDER BY ' . implode(', ', $order) : '';
    }
}
