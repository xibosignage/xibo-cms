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

namespace Xibo\Controller;

use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Report
 * @package Xibo\Controller
 */
class ScheduleReport extends Base
{
    public function __construct(
        private readonly ReportServiceInterface $reportService,
        private readonly ReportScheduleFactory $reportScheduleFactory,
        private readonly SavedReportFactory $savedReportFactory,
    ) {
    }

    /// //<editor-fold desc="Report Schedules">

    /**
     * Report Schedule Grid
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleGrid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $reportSchedules = $this->reportScheduleFactory->query(
            $this->gridRenderSort($sanitizedQueryParams, $this->isJson($request)),
            $this->getScheduleReportFilters($sanitizedQueryParams)
        );

        foreach ($reportSchedules as $reportSchedule) {
            $this->decorateScheduleReport($reportSchedule);
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->reportScheduleFactory->countLast())
            ->withJson($reportSchedules);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id);
        $this->decorateScheduleReport($reportSchedule);

        return $response
            ->withStatus(200)
            ->withJson($reportSchedule);
    }

    /**
     * Report Schedule Reset
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function reportScheduleReset(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id);

        $this->getLog()->debug('Reset Report Schedule: '.$reportSchedule->name);

        // Go back to previous run date
        $reportSchedule->lastSavedReportId = 0;
        $reportSchedule->lastRunDt = $reportSchedule->previousRunDt;
        $reportSchedule->save();

        // Return
        return $response->withStatus(204);
    }

    /**
     * Report Schedule Add
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleAdd(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $name = $sanitizedParams->getString('name');
        $reportName = $request->getParam('reportName', null);
        $fromDt = $sanitizedParams->getDate('fromDt', ['default' => 0]);
        $toDt = $sanitizedParams->getDate('toDt', ['default' => 0]);
        $today = Carbon::now()->startOfDay()->format('U');

        // from and todt should be greater than today
        if (!empty($fromDt)) {
            $fromDt = $fromDt->format('U');
            if ($fromDt < $today) {
                throw new InvalidArgumentException(__('Start time cannot be earlier than today'), 'fromDt');
            }
        }
        if (!empty($toDt)) {
            $toDt = $toDt->format('U');
            if ($toDt < $today) {
                throw new InvalidArgumentException(__('End time cannot be earlier than today'), 'toDt');
            }
        }

        $this->getLog()->debug('Add Report Schedule: '. $name);

        // Set Report Schedule form data
        $result = $this->reportService->setReportScheduleFormData($reportName, $request);

        $reportSchedule = $this->reportScheduleFactory->createEmpty();
        $reportSchedule->name = $name;
        $reportSchedule->lastSavedReportId = 0;
        $reportSchedule->reportName = $reportName;
        $reportSchedule->filterCriteria = $result['filterCriteria'];
        $reportSchedule->schedule = $result['schedule'];
        $reportSchedule->lastRunDt = 0;
        $reportSchedule->previousRunDt = 0;
        $reportSchedule->fromDt = $fromDt;
        $reportSchedule->toDt = $toDt;
        $reportSchedule->userId = $this->getUser()->userId;
        $reportSchedule->createdDt = Carbon::now()->format('U');

        $reportSchedule->save();

        // Return
        return $response
            ->withStatus(201)
            ->withJson($reportSchedule);
    }

    /**
     * Report Schedule Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleEdit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        if ($reportSchedule->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $name = $sanitizedParams->getString('name');
        $fromDt = $sanitizedParams->getDate('fromDt', ['default' => 0]);
        $toDt = $sanitizedParams->getDate('toDt', ['default' => 0]);
        $today = Carbon::now()->startOfDay()->format('U');

        // from and todt should be greater than today
        if (!empty($fromDt)) {
            $fromDt = $fromDt->format('U');
            if ($fromDt < $today) {
                throw new InvalidArgumentException(
                    __('Start time cannot be earlier than today'),
                    'fromDt'
                );
            }
        }
        if (!empty($toDt)) {
            $toDt = $toDt->format('U');
            if ($toDt < $today) {
                throw new InvalidArgumentException(
                    __('End time cannot be earlier than today'),
                    'toDt'
                );
            }
        }

        $reportSchedule->name = $name;
        $reportSchedule->fromDt = $fromDt;
        $reportSchedule->toDt = $toDt;
        $reportSchedule->save();

        // Return
        return $response
            ->withStatus(200)
            ->withJson($reportSchedule);
    }

    /**
     * Report Schedule Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleDelete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));
        }

        try {
            $reportSchedule->delete();
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException(
                __('Report schedule cannot be deleted.
                 Please ensure there are no saved reports against the schedule.'),
                'reportScheduleId'
            );
        }

        // Return
        return $response->withJson(204);
    }

    /**
     * Report Schedule Delete All Saved Report
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleDeleteAllSavedReport(
        Request $request,
        Response $response,
        $id
    ): Response|ResponseInterface {
        $reportSchedule = $this->reportScheduleFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($reportSchedule)) {
            throw new AccessDeniedException(
                __('You do not have permissions to delete the saved report of this report schedule')
            );
        }

        // Get all saved reports of the report schedule
        $savedReports = $this->savedReportFactory->query(
            null,
            [
                'reportScheduleId' => $reportSchedule->reportScheduleId
            ]
        );


        foreach ($savedReports as $savedreport) {
            try {
                $savedreport->load();

                // Delete
                $savedreport->delete();
            } catch (\RuntimeException $e) {
                throw new InvalidArgumentException(__('Saved report cannot be deleted'), 'savedReportId');
            }
        }

        // Return
        return $response->withStatus(204);
    }

    /**
     * Report Schedule Toggle Active
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleToggleActive(
        Request $request,
        Response $response,
        int $id
    ): Response|ResponseInterface {
        $reportSchedule = $this->reportScheduleFactory->getById($id);

        if (!$this->getUser()->checkEditable($reportSchedule)) {
            throw new AccessDeniedException(
                __('You do not have permissions to pause/resume this report schedule')
            );
        }

        if ($reportSchedule->isActive == 1) {
            $reportSchedule->isActive = 0;
        } else {
            $reportSchedule->isActive = 1;
        }

        $reportSchedule->save();

        // Return
        return $response->withStatus(204);
    }

    /**
     * @param SanitizerInterface $params
     * @return array
     */
    private function getScheduleReportFilters(SanitizerInterface $params): array
    {
        return $this->gridRenderFilter([
            'name' => $params->getString('name'),
            'useRegexForName' => $params->getCheckbox('useRegexForName'),
            'userId' => $params->getInt('userId'),
            'reportScheduleId' => $params->getInt('reportScheduleId'),
            'reportName' => $params->getString('reportName'),
            'onlyMySchedules' => $params->getCheckbox('onlyMySchedules'),
            'logicalOperatorName' => $params->getString('logicalOperatorName'),
        ], $params);
    }

    /**
     * @param ReportSchedule $reportSchedule
     * @return void
     * @throws GeneralException
     * @throws NotFoundException
     */
    private function decorateScheduleReport(ReportSchedule $reportSchedule): void
    {
        $cron = new \Cron\CronExpression($reportSchedule->schedule);

        if ($reportSchedule->lastRunDt == 0) {
            $nextRunDt = Carbon::now()->format('U');
        } else {
            $nextRunDt = $cron->getNextRunDate(Carbon::createFromTimestamp($reportSchedule->lastRunDt))
                ->format('U');
        }

        $reportSchedule->setUnmatchedProperty('nextRunDt', $nextRunDt);

        // We get the report description
        try {
            $reportSchedule->setUnmatchedProperty('reportNameId', $reportSchedule->reportName);
            $reportSchedule->reportName =
                $this->reportService->getReportByName($reportSchedule->reportName)->description;
        } catch (NotFoundException $notFoundException) {
            $reportSchedule->setUnmatchedProperty('reportNameId', $reportSchedule->reportName);
            $reportSchedule->reportName = __('Unknown or removed report.');
        }

        switch ($reportSchedule->schedule) {
            case ReportSchedule::$SCHEDULE_DAILY:
                $reportSchedule->schedule = __('Run once a day, midnight');
                break;

            case ReportSchedule::$SCHEDULE_WEEKLY:
                $reportSchedule->schedule = __('Run once a week, midnight on Monday');

                break;

            case ReportSchedule::$SCHEDULE_MONTHLY:
                $reportSchedule->schedule = __('Run once a month, midnight, first of month');

                break;

            case ReportSchedule::$SCHEDULE_YEARLY:
                $reportSchedule->schedule = __('Run once a year, midnight, Jan. 1');

                break;
        }

        switch ($reportSchedule->isActive) {
            case 1:
                $reportSchedule->setUnmatchedProperty(
                    'isActiveDescription',
                    __('This report schedule is active')
                );
                break;

            default:
                $reportSchedule->setUnmatchedProperty(
                    'isActiveDescription',
                    __('This report schedule is paused')
                );
        }

        if ($reportSchedule->getLastSavedReportId() > 0) {
            $lastSavedReport = $this->savedReportFactory->getById($reportSchedule->getLastSavedReportId());
            $reportSchedule->setUnmatchedProperty('schemaVersion', $lastSavedReport->schemaVersion);
        }

        // User permissions
        $reportSchedule->setUnmatchedProperty(
            'userPermissions',
            $this->getUser()->getPermission($reportSchedule)
        );
    }
    //</editor-fold>
}
