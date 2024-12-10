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
use Xibo\Factory\ApplicationRequestsFactory;
use Xibo\Factory\AuditLogFactory;
use Xibo\Factory\LogFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class ApiRequests implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

    /** @var LogFactory */
    private $logFactory;

    /** @var AuditLogFactory */
    private $auditLogFactory;

    /** @var ApplicationRequestsFactory */
    private $apiRequestsFactory;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->logFactory = $container->get('logFactory');
        $this->auditLogFactory = $container->get('auditLogFactory');
        $this->apiRequestsFactory = $container->get('apiRequestsFactory');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'apirequests-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'apirequests-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm(): ReportForm
    {
        return new ReportForm(
            'apirequests-report-form',
            'apirequests',
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
        $data['reportName'] = 'apirequests';

        return [
            'template' => 'apirequests-schedule-form-add',
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
            __('%s API requests %s log report for User'),
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
            'logType' => $json['metadata']['logType']
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

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determines whether the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.

        // Range
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Expect dates to be provided.
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');

        $type = $sanitizedParams->getString('type');

        $metadata = [
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))
                ->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))
                ->format(DateFormatHelper::getSystemFormat()),
            'logType' => $type,
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
             `auditlog`.requestId,
             `application_requests_history`.applicationId,
             `application_requests_history`.url,
             `application_requests_history`.method,
             `application_requests_history`.startTime,
             `oauth_clients`.name AS applicationName
                FROM `auditlog`
                    INNER JOIN `user` 
                        ON `user`.`userId` = `auditlog`.`userId`
                    INNER JOIN `application_requests_history` 
                        ON `application_requests_history`.`requestId` = `auditlog`.`requestId`
                    INNER JOIN `oauth_clients` 
                        ON `oauth_clients`.id = `application_requests_history`.applicationId
             WHERE `auditlog`.logDate BETWEEN :fromDt AND :toDt
             ';

            if ($sanitizedParams->getInt('userId') !== null) {
                $sql .= ' AND `auditlog`.`userId` = :userId';
                $params['userId'] = $sanitizedParams->getInt('userId');
            }

            if ($sanitizedParams->getInt('requestId') !== null) {
                $sql .= ' AND `auditlog`.`requestId` = :requestId';
                $params['requestId'] = $sanitizedParams->getInt('requestId');
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
                    'applicationId',
                    $row['applicationId']
                );

                $auditRecord->setUnmatchedProperty(
                    'applicationName',
                    $row['applicationName']
                );

                $auditRecord->setUnmatchedProperty(
                    'url',
                    $row['url']
                );

                $auditRecord->setUnmatchedProperty(
                    'method',
                    $row['method']
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
             `log`.`requestId`,
             `user`.`userName`,
             `application_requests_history`.applicationId,
             `application_requests_history`.url,
             `application_requests_history`.method,
             `application_requests_history`.startTime,
             `oauth_clients`.name AS applicationName
                FROM `log`
                    INNER JOIN `user`
                        ON `user`.`userId` = `log`.`userId`
                    INNER JOIN `application_requests_history`
                        ON `application_requests_history`.`requestId` = `log`.`requestId`
                    INNER JOIN `oauth_clients` 
                        ON `oauth_clients`.id = `application_requests_history`.applicationId
             WHERE `log`.logDate BETWEEN :fromDt AND :toDt
             ';

            if ($sanitizedParams->getInt('userId') !== null) {
                $sql .= ' AND `log`.`userId` = :userId';
                $params['userId'] = $sanitizedParams->getInt('userId');
            }

            if ($sanitizedParams->getInt('requestId') !== null) {
                $sql .= ' AND `log`.`requestId` = :requestId';
                $params['requestId'] = $sanitizedParams->getInt('requestId');
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
                    'applicationId',
                    $row['applicationId']
                );

                $logRecord->setUnmatchedProperty(
                    'applicationName',
                    $row['applicationName']
                );

                $logRecord->setUnmatchedProperty(
                    'url',
                    $row['url']
                );

                $logRecord->setUnmatchedProperty(
                    'method',
                    $row['method']
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
             `application_requests_history`.applicationId,
             `application_requests_history`.requestId,
             `application_requests_history`.userId,
             `application_requests_history`.url,
             `application_requests_history`.method,
             `application_requests_history`.startTime,
             `oauth_clients`.name AS applicationName,
             `user`.`userName`
                FROM `application_requests_history`
                    INNER JOIN `user`
                        ON `user`.`userId` = `application_requests_history`.`userId`
                    INNER JOIN `oauth_clients` 
                        ON `oauth_clients`.id = `application_requests_history`.applicationId
             WHERE `application_requests_history`.startTime BETWEEN :fromDt AND :toDt
             ';

            if ($sanitizedParams->getInt('userId') !== null) {
                $sql .= ' AND `application_requests_history`.`userId` = :userId';
                $params['userId'] = $sanitizedParams->getInt('userId');
            }

            // Sorting?
            $sortOrder = $this->gridRenderSort($sanitizedParams);

            if (is_array($sortOrder)) {
                $sql .= ' ORDER BY ' . implode(',', $sortOrder);
            }

            $rows = [];

            foreach ($this->store->select($sql, $params) as $row) {
                $apiRequestRecord = $this->apiRequestsFactory->createEmpty()->hydrate($row);

                $apiRequestRecord->setUnmatchedProperty(
                    'userName',
                    $row['userName']
                );

                $apiRequestRecord->setUnmatchedProperty(
                    'applicationName',
                    $row['applicationName']
                );

                $rows[] = $apiRequestRecord;
            }

            return new ReportResult(
                $metadata,
                $rows,
                count($rows),
            );
        }
    }
}
