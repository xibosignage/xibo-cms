<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

namespace Xibo\Controller;

use Xibo\Entity\Media;
use Xibo\Entity\ReportSchedule;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class Report
 * @package Xibo\Controller
 */
class Report extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var TimeSeriesStoreInterface
     */
    private $timeSeriesStore;

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
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param ReportServiceInterface $reportService
     * @param ReportScheduleFactory $reportScheduleFactory
     * @param SavedReportFactory $savedReportFactory
     * @param MediaFactory $mediaFactory
     * @param LayoutFactory $layoutFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $timeSeriesStore, $reportService, $reportScheduleFactory, $savedReportFactory, $mediaFactory, $layoutFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->reportService = $reportService;
        $this->reportScheduleFactory = $reportScheduleFactory;
        $this->savedReportFactory = $savedReportFactory;
        $this->mediaFactory = $mediaFactory;
        $this->layoutFactory = $layoutFactory;
    }

    /// //<editor-fold desc="Report Schedules">

    /**
     * Report Schedule Grid
     */
    public function reportScheduleGrid()
    {
        $reportSchedules = $this->reportScheduleFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'name' => $this->getSanitizer()->getString('name'),
        ]));

        /** @var \Xibo\Entity\ReportSchedule $reportSchedule */
        foreach ($reportSchedules as $reportSchedule) {

            $reportSchedule->includeProperty('buttons');

            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);
            $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $reportSchedule->lastRunDt))->format('U');
            $reportSchedule->nextRunDt = $nextRunDt;

            $reportSchedule->reportName = $this->reportService->getReportByName($reportSchedule->reportName)->description;

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

            // Edit
            $reportSchedule->buttons[] = [
                'id' => 'reportSchedule_edit_button',
                'url' => $this->urlFor('reportschedule.edit.form', ['id' => $reportSchedule->reportScheduleId]),
                'text' => __('Edit')
            ];

            // Delete
            $reportSchedule->buttons[] = [
                'id' => 'reportSchedule_delete_button',
                'url' => $this->urlFor('reportschedule.delete.form', ['id' => $reportSchedule->reportScheduleId]),
                'text' => __('Delete')
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->reportScheduleFactory->countLast();
        $this->getState()->setData($reportSchedules);
    }

    /**
     * Report Schedule Add
     */
    public function reportScheduleAdd()
    {

        $name = $this->getSanitizer()->getString('name');
        $reportName = $this->getSanitizer()->getParam('reportName', null);

        $this->getLog()->debug('Add Report Schedule: '. $name);

        // Set Report Schedule form data
        $result = $this->reportService->setReportScheduleFormData($reportName);

        $reportSchedule = $this->reportScheduleFactory->createEmpty();
        $reportSchedule->name = $name;
        $reportSchedule->reportName = $reportName;
        $reportSchedule->filterCriteria = $result['filterCriteria'];
        $reportSchedule->schedule = $result['schedule'];
        $reportSchedule->lastRunDt = 0;
        $reportSchedule->userId = $this->getUser()->userId;
        $reportSchedule->createdDt = $this->getDate()->getLocalDate(null, 'U');

        $reportSchedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Report Schedule'),
            'id' => $reportSchedule->reportScheduleId,
            'data' => $reportSchedule
        ]);
    }

    /**
     * Report Schedule Edit
     * @param $reportScheduleId
     */
    public function reportScheduleEdit($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if ($reportSchedule->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $reportSchedule->name = $this->getSanitizer()->getString('name');
        $reportSchedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $reportSchedule->name),
            'id' => $reportSchedule->reportScheduleId,
            'data' => $reportSchedule
        ]);
    }

    /**
     * Report Schedule Delete
     */
    public function reportScheduleDelete($reportScheduleId)
    {

        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if (!$this->getUser()->checkDeleteable($reportSchedule))
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));

        $reportSchedule->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $reportSchedule->name)
        ]);
    }

    /**
     * Displays the Report Schedule Page
     */
    public function displayReportSchedulePage()
    {
        // Call to render the template
        $this->getState()->template = 'report-schedule-page';
    }

    /**
     * Displays an Add form
     */
    public function addReportScheduleForm()
    {

        $reportName = $this->getSanitizer()->getParam('reportName', null);

        // Populate form title and hidden fields
        $formData = $this->reportService->getReportScheduleFormData($reportName);

        $data = ['filters' => []];

        $data['filters'][] = ['name'=> 'Daily', 'filter'=> 'daily'];
        $data['filters'][] = ['name'=> 'Weekly', 'filter'=> 'weekly'];
        $data['filters'][] = ['name'=> 'Monthly', 'filter'=> 'monthly'];
        $data['filters'][] = ['name'=> 'Yearly', 'filter'=> 'yearly'];

        $data['formTitle'] = $formData['title'];
        $data['hiddenFields'] = (count($formData['hiddenFields']) > 0 ) ? json_encode($formData['hiddenFields']) : '';
        $data['reportName'] = $reportName;

        $this->getState()->template = 'report-schedule-form-add';
        $this->getState()->setData($data);

    }

    /**
     * Report Schedule Edit Form
     */
    public function editReportScheduleForm($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        $this->getState()->template = 'reportschedule-form-edit';
        $this->getState()->setData([
            'reportSchedule' => $reportSchedule
        ]);
    }

    /**
     * Report Schedule Delete Form
     */
    public function deleteReportScheduleForm($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if (!$this->getUser()->checkDeleteable($reportSchedule))
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-delete';
        $this->getState()->setData($data);

    }

    //</editor-fold>

    //<editor-fold desc="Saved report">

    /**
     * Saved report Grid
     */
    public function savedReportGrid()
    {
        /*
         * //TODO:
         *
         */

        $savedReports = $this->savedReportFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'saveAs' => $this->getSanitizer()->getString('saveAs'),
        ]));

        foreach ($savedReports as $savedReport) {
            /** @var \Xibo\Entity\SavedReport $savedReport */

            $savedReport->includeProperty('buttons');

            $savedReport->buttons[] = [
                'id' => 'button_show_report.now',
                'class' => 'XiboRedirectButton',
                'url' => $this->urlFor('savedreport.preview', ['id' => $savedReport->savedReportId, 'name' => $savedReport->reportName] ),
                'text' => __('Preview report')
            ];

            // Delete
            $savedReport->buttons[] = [
                'id' => 'savedReport_delete_button',
                'url' => $this->urlFor('savedreport.delete.form', ['id' => $savedReport->savedReportId]),
                'text' => __('Delete')
            ];
        }
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->savedReportFactory->countLast();
        $this->getState()->setData($savedReports);
    }

    /**
     * Displays the Saved Report Page
     */
    public function displaySavedReportPage()
    {
        // Call to render the template
        $this->getState()->template = 'saved-report-page';
    }

    /**
     * Report Schedule Delete Form
     */
    public function deleteSavedReportForm($savedreportId)
    {
        $savedReport = $this->savedReportFactory->getById($savedreportId);

        if (!$this->getUser()->checkDeleteable($savedReport))
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));

        $data = [
            'savedReport' => $savedReport
        ];

        $this->getState()->template = 'savedreport-form-delete';
        $this->getState()->setData($data);

    }

    /**
     * Saved Report Delete
     */
    public function savedReportDelete($savedreportId)
    {

        $savedReport = $this->savedReportFactory->getById($savedreportId);

        /** @var Media $media */
        $media = $this->mediaFactory->getById($savedReport->mediaId);

        if (!$this->getUser()->checkDeleteable($savedReport))
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));

        $savedReport->load();
        $media->load();

        // Delete
        $savedReport->delete();
        $media->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $savedReport->saveAs)
        ]);
    }

    /**
     * Returns a Saved Report's preview
     * @param int $savedreportId
     * @throws XiboException
     */
    public function savedReportPreview($savedreportId, $reportName)
    {
        // Retrieve the saved report result in array
        $results = $this->reportService->getSavedReportResults($savedreportId, $reportName);

        $this->getState()->template = $results['template'];
        $this->getState()->setData($results['chartData']);
    }

    //</editor-fold>

    /// //<editor-fold desc="Ad hoc reports">

    /**
     * Displays an Ad Hoc Report form
     */
    public function getReportForm($reportName)
    {

        $this->getLog()->debug('Get report name: '.$reportName);

        // Get the report Class from the Json File
        $className = $this->reportService->getReportClass($reportName);

        // Create the report object
        $object = $this->reportService->createReportObject($className);

        // Get the twig file name of the report form
        $template =  $object->getReportForm();

        // Show the twig
        $this->getState()->template = $template;
        $this->getState()->setData([
            'reportName' => $reportName
        ]);
    }

    /**
     * Displays Ad Hoc Report data in charts
     */
    public function getReportData($reportName)
    {
        // Get the report Class from the Json File
        $className = $this->reportService->getReportClass($reportName);

        // Create the report object
        $object = $this->reportService->createReportObject($className);

        // Return data to build chart
        $results =  $object->getResults(null);
        $this->getState()->extra = $results;
    }

    //</editor-fold>
}