<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Report
 * @package Xibo\Controller
 */
class ScheduleReport extends Base
{
    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /**
     * @var ReportScheduleFactory
     */
    private $reportScheduleFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var Twig
     */
    private $view;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param Twig $view
     * @param ReportServiceInterface $reportService
     * @param ReportScheduleFactory $reportScheduleFactory
     * @param SavedReportFactory $savedReportFactory
     * @param MediaFactory $mediaFactory
     * @param UserFactory $userFactory
     */
    public function __construct($reportService, $reportScheduleFactory, $savedReportFactory, $mediaFactory, $userFactory)
    {
        $this->reportService = $reportService;
        $this->reportScheduleFactory = $reportScheduleFactory;
        $this->savedReportFactory = $savedReportFactory;
        $this->mediaFactory = $mediaFactory;
        $this->userFactory = $userFactory;
    }

    /// //<editor-fold desc="Report Schedules">

    /**
     * Report Schedule Grid
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleGrid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $reportSchedules = $this->reportScheduleFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter([
            'name' => $sanitizedQueryParams->getString('name'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'userId' => $sanitizedQueryParams->getInt('userId'),
            'reportScheduleId' => $sanitizedQueryParams->getInt('reportScheduleId'),
            'reportName' => $sanitizedQueryParams->getString('reportName'),
            'onlyMySchedules' => $sanitizedQueryParams->getCheckbox('onlyMySchedules'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
        ], $sanitizedQueryParams));

        /** @var \Xibo\Entity\ReportSchedule $reportSchedule */
        foreach ($reportSchedules as $reportSchedule) {
            if ($this->isApi($request)) {
                continue;
            }

            $reportSchedule->includeProperty('buttons');

            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);

            if ($reportSchedule->lastRunDt == 0) {
                $nextRunDt = Carbon::now()->format('U');
            } else {
                $nextRunDt = $cron->getNextRunDate(Carbon::createFromTimestamp($reportSchedule->lastRunDt))->format('U');
            }

            $reportSchedule->setUnmatchedProperty('nextRunDt', $nextRunDt);

            // Ad hoc report name
            $adhocReportName = $reportSchedule->reportName;

            // We get the report description
            try {
                $reportSchedule->reportName = $this->reportService->getReportByName($reportSchedule->reportName)->description;
            } catch (NotFoundException $notFoundException) {
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
                    $reportSchedule->setUnmatchedProperty('isActiveDescription', __('This report schedule is active'));
                    break;

                default:
                    $reportSchedule->setUnmatchedProperty('isActiveDescription', __('This report schedule is paused'));
            }

            if ($reportSchedule->getLastSavedReportId() > 0) {
                $lastSavedReport = $this->savedReportFactory->getById($reportSchedule->getLastSavedReportId());

                // Hide this for schema version 1
                if ($lastSavedReport->schemaVersion != 1) {
                    // Open Last Saved Report
                    $reportSchedule->buttons[] = [
                        'id' => 'reportSchedule_lastsaved_report_button',
                        'class' => 'XiboRedirectButton',
                        'url' => $this->urlFor($request, 'savedreport.open', ['id' => $lastSavedReport->savedReportId, 'name' => $lastSavedReport->reportName]),
                        'text' => __('Open last saved report')
                    ];
                }
            }

            // Back to Reports
            $reportSchedule->buttons[] = [
                'id' => 'reportSchedule_goto_report_button',
                'class' => 'XiboRedirectButton',
                'url' => $this->urlFor($request, 'report.form', ['name' => $adhocReportName]),
                'text' => __('Back to Reports')
            ];
            $reportSchedule->buttons[] = ['divider' => true];

            // Edit
            if ($this->getUser()->featureEnabled('report.scheduling')) {
                $reportSchedule->buttons[] = [
                    'id' => 'reportSchedule_edit_button',
                    'url' => $this->urlFor($request, 'reportschedule.edit.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Edit')
                ];
            }

            // Reset to previous run
            if ($this->getUser()->isSuperAdmin()) {
                $reportSchedule->buttons[] = [
                    'id' => 'reportSchedule_reset_button',
                    'url' => $this->urlFor($request, 'reportschedule.reset.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Reset to previous run')
                ];
            }

            // Delete
            if ($this->getUser()->featureEnabled('report.scheduling')
                && $this->getUser()->checkDeleteable($reportSchedule)) {
                // Show the delete button
                $reportSchedule->buttons[] = [
                    'id' => 'reportschedule_button_delete',
                    'url' => $this->urlFor($request, 'reportschedule.delete.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request, 'reportschedule.delete', ['id' => $reportSchedule->reportScheduleId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'reportschedule_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $reportSchedule->name]
                    ]
                ];
            }

            // Toggle active
            if ($this->getUser()->featureEnabled('report.scheduling')) {
                $reportSchedule->buttons[] = [
                    'id' => 'reportSchedule_toggleactive_button',
                    'url' => $this->urlFor($request, 'reportschedule.toggleactive.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => ($reportSchedule->isActive == 1) ? __('Pause') : __('Resume')
                ];
            }

            // Delete all saved report
            $savedreports = $this->savedReportFactory->query(null, ['reportScheduleId'=> $reportSchedule->reportScheduleId]);
            if ((count($savedreports) > 0)
                && $this->getUser()->checkDeleteable($reportSchedule)
                && $this->getUser()->featureEnabled('report.saving')
            ) {
                $reportSchedule->buttons[] = ['divider' => true];

                $reportSchedule->buttons[] = array(
                    'id' => 'reportschedule_button_delete_all',
                    'url' => $this->urlFor($request, 'reportschedule.deleteall.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Delete all saved reports'),
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->reportScheduleFactory->countLast();
        $this->getState()->setData($reportSchedules);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Reset
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleReset(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id);

        $this->getLog()->debug('Reset Report Schedule: '.$reportSchedule->name);

        // Go back to previous run date
        $reportSchedule->lastSavedReportId = 0;
        $reportSchedule->lastRunDt = $reportSchedule->previousRunDt;
        $reportSchedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => 'Success'
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Add
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleAdd(Request $request, Response $response)
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
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Report Schedule'),
            'id' => $reportSchedule->reportScheduleId,
            'data' => $reportSchedule
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleEdit(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        if ($reportSchedule->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

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

        $reportSchedule->name = $name;
        $reportSchedule->fromDt = $fromDt;
        $reportSchedule->toDt = $toDt;
        $reportSchedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $reportSchedule->name),
            'id' => $reportSchedule->reportScheduleId,
            'data' => $reportSchedule
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleDelete(Request $request, Response $response, $id)
    {

        $reportSchedule = $this->reportScheduleFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));
        }

        try {
            $reportSchedule->delete();
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException(__('Report schedule cannot be deleted. Please ensure there are no saved reports against the schedule.'), 'reportScheduleId');
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $reportSchedule->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Delete All Saved Report
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleDeleteAllSavedReport(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $reportSchedule = $this->reportScheduleFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to delete the saved report of this report schedule'));
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
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted all saved reports of %s'), $reportSchedule->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Toggle Active
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function reportScheduleToggleActive(Request $request, Response $response, $id)
    {

        $reportSchedule = $this->reportScheduleFactory->getById($id);

        if (!$this->getUser()->checkEditable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to pause/resume this report schedule'));
        }

        if ($reportSchedule->isActive == 1) {
            $reportSchedule->isActive = 0;
            $msg = sprintf(__('Paused %s'), $reportSchedule->name);
        } else {
            $reportSchedule->isActive = 1;
            $msg = sprintf(__('Resumed %s'), $reportSchedule->name);
        }

        $reportSchedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => $msg
        ]);

        return $this->render($request, $response);
    }

    /**
     * Displays the Report Schedule Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayReportSchedulePage(Request $request, Response $response)
    {
        $reportsList = $this->reportService->listReports();
        $availableReports = [];
        foreach ($reportsList as $reports) {
            foreach ($reports as $report) {
                $availableReports[] = $report;
            }
        }

        // Call to render the template
        $this->getState()->template = 'report-schedule-page';
        $this->getState()->setData([
            'availableReports' => $availableReports
        ]);

        return $this->render($request, $response);
    }

    /**
     * Displays an Add form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addReportScheduleForm(Request $request, Response $response)
    {

        $reportName = $request->getParam('reportName', null);

        // Populate form title and hidden fields
        $formData = $this->reportService->getReportScheduleFormData($reportName, $request);

        $template = $formData['template'];
        $this->getState()->template = $template;
        $this->getState()->setData($formData['data']);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editReportScheduleForm(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        if ($reportSchedule->fromDt > 0) {
            $reportSchedule->fromDt = Carbon::createFromTimestamp($reportSchedule->fromDt)->format(DateFormatHelper::getSystemFormat());
        } else {
            $reportSchedule->fromDt = '';
        }

        if ($reportSchedule->toDt > 0) {
            $reportSchedule->toDt = Carbon::createFromTimestamp($reportSchedule->toDt)->format(DateFormatHelper::getSystemFormat());
        } else {
            $reportSchedule->toDt = '';
        }

        $this->getState()->template = 'reportschedule-form-edit';
        $this->getState()->setData([
            'reportSchedule' => $reportSchedule
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Reset Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function resetReportScheduleForm(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        // Only admin can reset it
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException(__('You do not have permissions to reset this report schedule'));
        }

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-reset';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteReportScheduleForm(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        if (!$this->getUser()->checkDeleteable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));
        }

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-delete';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Delete All Saved Report Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteAllSavedReportReportScheduleForm(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        if (!$this->getUser()->checkDeleteable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to delete saved reports of this report schedule'));
        }

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-deleteall';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Toggle Active Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function toggleActiveReportScheduleForm(Request $request, Response $response, $id)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($id, 0);

        if (!$this->getUser()->checkEditable($reportSchedule)) {
            throw new AccessDeniedException(__('You do not have permissions to pause/resume this report schedule'));
        }

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-toggleactive';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    //</editor-fold>
}
