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
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
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
     * @var UserFactory
     */
    private $userFactory;

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
     * @param UserFactory $userFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $timeSeriesStore, $reportService, $reportScheduleFactory, $savedReportFactory, $mediaFactory, $userFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->reportService = $reportService;
        $this->reportScheduleFactory = $reportScheduleFactory;
        $this->savedReportFactory = $savedReportFactory;
        $this->mediaFactory = $mediaFactory;
        $this->userFactory = $userFactory;
    }

    /// //<editor-fold desc="Report Schedules">

    /**
     * Report Schedule Grid
     */
    public function reportScheduleGrid()
    {
        $reportSchedules = $this->reportScheduleFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'name' => $this->getSanitizer()->getString('name'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'reportScheduleId' => $this->getSanitizer()->getInt('reportScheduleId'),
            'reportName' => $this->getSanitizer()->getString('reportName')
        ]));

        /** @var \Xibo\Entity\ReportSchedule $reportSchedule */
        foreach ($reportSchedules as $reportSchedule) {

            $reportSchedule->includeProperty('buttons');

            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);

            if ($reportSchedule->lastRunDt == 0) {
                $nextRunDt = $this->getDate()->parse()->format('U');
            } else {
                $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $reportSchedule->lastRunDt))->format('U');
            }

            $reportSchedule->nextRunDt = $nextRunDt;

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
                    $reportSchedule->isActiveDescription = __('This report schedule is active');
                    break;

                default:
                    $reportSchedule->isActiveDescription = __('This report schedule is paused');
            }

            if ($reportSchedule->getLastSavedReportId() > 0) {

                $lastSavedReport = $this->savedReportFactory->getById($reportSchedule->getLastSavedReportId());

                // Open Last Saved Report
                $reportSchedule->buttons[] = [
                    'id' => 'reportSchedule_lastsaved_report_button',
                    'class' => 'XiboRedirectButton',
                    'url' => $this->urlFor('savedreport.open', ['id' => $lastSavedReport->savedReportId, 'name' => $lastSavedReport->reportName] ),
                    'text' => __('Open last saved report')
                ];
            }

            // Back to Reports
            $reportSchedule->buttons[] = [
                'id' => 'reportSchedule_goto_report_button',
                'class' => 'XiboRedirectButton',
                'url' => $this->urlFor('report.form', ['name' => $adhocReportName] ),
                'text' => __('Back to Reports')
            ];
            $reportSchedule->buttons[] = ['divider' => true];

            // Edit
            $reportSchedule->buttons[] = [
                'id' => 'reportSchedule_edit_button',
                'url' => $this->urlFor('reportschedule.edit.form', ['id' => $reportSchedule->reportScheduleId]),
                'text' => __('Edit')
            ];

            // Reset to previous run
            if ($this->getUser()->isSuperAdmin()) {
                $reportSchedule->buttons[] = [
                    'id' => 'reportSchedule_reset_button',
                    'url' => $this->urlFor('reportschedule.reset.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Reset to previous run')
                ];
            }

            // Delete
            if ($this->getUser()->checkDeleteable($reportSchedule)) {
                // Show the delete button
                $reportSchedule->buttons[] = array(
                    'id' => 'reportschedule_button_delete',
                    'url' => $this->urlFor('reportschedule.delete.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('reportschedule.delete', ['id' => $reportSchedule->reportScheduleId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'reportschedule_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $reportSchedule->name),
                    )
                );
            }

            // Toggle active
            $reportSchedule->buttons[] = [
                'id' => 'reportSchedule_toggleactive_button',
                'url' => $this->urlFor('reportschedule.toggleactive.form', ['id' => $reportSchedule->reportScheduleId]),
                'text' => ($reportSchedule->isActive == 1) ? __('Pause') : __('Resume')
            ];

            // Delete all saved report
            $savedreports = $this->savedReportFactory->query(null, ['reportScheduleId'=> $reportSchedule->reportScheduleId]);
            if ((count($savedreports) > 0)  && $this->getUser()->checkDeleteable($reportSchedule)) {

                $reportSchedule->buttons[] = ['divider' => true];

                $reportSchedule->buttons[] = array(
                    'id' => 'reportschedule_button_delete_all',
                    'url' => $this->urlFor('reportschedule.deleteall.form', ['id' => $reportSchedule->reportScheduleId]),
                    'text' => __('Delete all saved reports'),
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->reportScheduleFactory->countLast();
        $this->getState()->setData($reportSchedules);
    }

    /**
     * Report Schedule Reset
     */
    public function reportScheduleReset($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        $this->getLog()->debug('Reset Report Schedule: '.$reportSchedule->name);

        // Go back to previous run date
        $reportSchedule->lastSavedReportId = 0;
        $reportSchedule->lastRunDt = $reportSchedule->previousRunDt;
        $reportSchedule->save();
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
        $reportSchedule->lastSavedReportId = 0;
        $reportSchedule->reportName = $reportName;
        $reportSchedule->filterCriteria = $result['filterCriteria'];
        $reportSchedule->schedule = $result['schedule'];
        $reportSchedule->lastRunDt = 0;
        $reportSchedule->previousRunDt = 0;
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

        try {
            $reportSchedule->delete();
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException(__('Report schedule cannot be deleted. Please ensure there are no saved reports against the schedule.'), 'reportScheduleId' );

        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $reportSchedule->name)
        ]);
    }

    /**
     * Report Schedule Delete All Saved Report
     */
    public function reportScheduleDeleteAllSavedReport($reportScheduleId)
    {

        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if (!$this->getUser()->checkDeleteable($reportSchedule))
            throw new AccessDeniedException(__('You do not have permissions to delete the saved report of this report schedule'));

        // Get all saved reports of the report schedule
        $savedReports = $this->savedReportFactory->query(null, ['reportScheduleId' => $reportScheduleId]);
        foreach ($savedReports as $savedreport) {
            try {
                /** @var Media $media */
                $media = $this->mediaFactory->getById($savedreport->mediaId);

                $savedreport->load();
                $media->load();

                // Delete
                $savedreport->delete();
                $media->delete();

            } catch (\RuntimeException $e) {
                throw new InvalidArgumentException(__('Saved report cannot be deleted'), 'savedReportId');
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted all saved reports of %s'), $reportSchedule->name)
        ]);
    }

    /**
     * Report Schedule Toggle Active
     */
    public function reportScheduleToggleActive($reportScheduleId)
    {

        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if (!$this->getUser()->checkEditable($reportSchedule))
            throw new AccessDeniedException(__('You do not have permissions to pause/resume this report schedule'));

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
    }

    /**
     * Displays the Report Schedule Page
     */
    public function displayReportSchedulePage()
    {
        // Call to render the template
        $this->getState()->template = 'report-schedule-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'availableReports' => $this->reportService->listReports()
        ]);
    }

    /**
     * Displays an Add form
     */
    public function addReportScheduleForm()
    {

        $reportName = $this->getSanitizer()->getParam('reportName', null);

        // Populate form title and hidden fields
        $formData = $this->reportService->getReportScheduleFormData($reportName);

        $template = $formData['template'];
        $this->getState()->template = $template;
        $this->getState()->setData($formData['data']);

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
     * Report Schedule Reset Form
     */
    public function resetReportScheduleForm($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        // Only admin can reset it
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException(__('You do not have permissions to reset this report schedule'));

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-reset';
        $this->getState()->setData($data);
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

    /**
     * Report Schedule Delete All Saved Report Form
     */
    public function deleteAllSavedReportReportScheduleForm($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if (!$this->getUser()->checkDeleteable($reportSchedule))
            throw new AccessDeniedException(__('You do not have permissions to delete saved reports of this report schedule'));

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-deleteall';
        $this->getState()->setData($data);

    }

    /**
     * Report Schedule Toggle Active Form
     */
    public function toggleActiveReportScheduleForm($reportScheduleId)
    {
        $reportSchedule = $this->reportScheduleFactory->getById($reportScheduleId);

        if (!$this->getUser()->checkEditable($reportSchedule))
            throw new AccessDeniedException(__('You do not have permissions to pause/resume this report schedule'));

        $data = [
            'reportSchedule' => $reportSchedule
        ];

        $this->getState()->template = 'reportschedule-form-toggleactive';
        $this->getState()->setData($data);

    }

    //</editor-fold>

    //<editor-fold desc="Saved report">

    /**
     * Saved report Grid
     */
    public function savedReportGrid()
    {
        $savedReports = $this->savedReportFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'saveAs' => $this->getSanitizer()->getString('saveAs'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'reportName' => $this->getSanitizer()->getString('reportName')
        ]));

        foreach ($savedReports as $savedReport) {
            /** @var \Xibo\Entity\SavedReport $savedReport */

            $savedReport->includeProperty('buttons');

            $savedReport->buttons[] = [
                'id' => 'button_show_report.now',
                'class' => 'XiboRedirectButton',
                'url' => $this->urlFor('savedreport.open', ['id' => $savedReport->savedReportId, 'name' => $savedReport->reportName] ),
                'text' => __('Open')
            ];
            $savedReport->buttons[] = ['divider' => true];

            $savedReport->buttons[] = [
                'id' => 'button_goto_report',
                'class' => 'XiboRedirectButton',
                'url' => $this->urlFor('report.form', ['name' => $savedReport->reportName] ),
                'text' => __('Back to Reports')
            ];

            $savedReport->buttons[] = [
                'id' => 'button_goto_schedule',
                'class' => 'XiboRedirectButton',
                'url' => $this->urlFor('reportschedule.view' ) . '?reportScheduleId=' . $savedReport->reportScheduleId. '&reportName='.$savedReport->reportName,
                'text' => __('Go to schedule')
            ];

            $savedReport->buttons[] = ['divider' => true];

            // Get report email template
            $emailTemplate = $this->reportService->getReportEmailTemplate($savedReport->reportName);

            if (!empty($emailTemplate)) {

                // Export Button
                $savedReport->buttons[] = [
                    'id' => 'button_export_report',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('savedreport.export', ['id' => $savedReport->savedReportId, 'name' => $savedReport->reportName] ),
                    'text' => __('Export as PDF')
                ];
            }

            // Delete
            if ($this->getUser()->checkDeleteable($savedReport)) {
                // Show the delete button
                $savedReport->buttons[] = array(
                    'id' => 'savedreport_button_delete',
                    'url' => $this->urlFor('savedreport.delete.form', ['id' => $savedReport->savedReportId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('savedreport.delete', ['id' => $savedReport->savedReportId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'savedreport_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $savedReport->saveAs),
                    )
                );
            }
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
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'availableReports' => $this->reportService->listReports()
        ]);
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
     * @param string $reportName
     */
    public function savedReportOpen($savedreportId, $reportName)
    {
        // Retrieve the saved report result in array
        $results = $this->reportService->getSavedReportResults($savedreportId, $reportName);

        $this->getState()->template = $results['template'];
        $this->getState()->setData($results['chartData']);
    }

    /**
     * Exports saved report as a PDF file
     * @param int $savedreportId
     * @param string $reportName
     * @throws XiboException
     */
    public function savedReportExport($savedreportId, $reportName)
    {
        $savedReport = $this->savedReportFactory->getById($savedreportId);

        // Retrieve the saved report result in array
        $savedReportData = $this->reportService->getSavedReportResults($savedreportId, $reportName);

        // Get the report config
        $report = $this->reportService->getReportByName($reportName);
        if ($report->output_type == 'chart') {

            $quickChartUrl = $this->getConfig()->getSetting('QUICK_CHART_URL');
            if (!empty($quickChartUrl)) {
                $script = $this->reportService->getReportChartScript($savedreportId, $reportName);
                $src = $quickChartUrl. "/chart?width=1000&height=300&c=".$script;
            } else {
                $placeholder = __('Chart could not be drawn because the CMS has not been configured with a Quick Chart URL.');
            }

        } else { // only for tablebased report

            $result = $savedReportData['chartData']['result'];
            $tableData =json_decode($result, true);
        }

        // Get report email template
        $emailTemplate = $this->reportService->getReportEmailTemplate($reportName);
        if (!empty($emailTemplate)) {

            // Save PDF attachment
            ob_start();
            // Render the template
            $this->app->render($emailTemplate,
                [
                    'header' => $report->description,
                    'logo' => $this->getConfig()->uri('img/xibologo.png', true),
                    'title' => $savedReport->saveAs,
                    'periodStart' => $savedReportData['chartData']['periodStart'],
                    'periodEnd' => $savedReportData['chartData']['periodEnd'],
                    'generatedOn' => $this->getDate()->parse($savedReport->generatedOn, 'U')->format('Y-m-d H:i:s'),
                    'tableData' => isset($tableData) ? $tableData : null,
                    'src' => isset($src) ? $src : null,
                    'placeholder' => isset($placeholder) ? $placeholder : null
                ]);
            $body = ob_get_contents();
            ob_end_clean();

            $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION'). 'temp/saved_report_'.$savedreportId.'.pdf';

            try {
                $mpdf = new \Mpdf\Mpdf([
                    'tempDir' => $this->getConfig()->getSetting('LIBRARY_LOCATION') . '/temp',
                    'orientation' => 'L',
                    'mode' => 'c',
                    'margin_left' => 20,
                    'margin_right' => 20,
                    'margin_top' => 20,
                    'margin_bottom' => 20,
                    'margin_header' => 5,
                    'margin_footer' => 15
                ]);
                $mpdf->setFooter('Page {PAGENO}') ;
                $mpdf->SetDisplayMode('fullpage');
                $stylesheet =  file_get_contents($this->getConfig()->uri('css/email-report.css', true));
                $mpdf->WriteHTML($stylesheet, 1);
                $mpdf->WriteHTML($body);
                $mpdf->Output($fileName, \Mpdf\Output\Destination::FILE);
            } catch (\Exception $error) {
                $this->getLog()->error($error->getMessage());
            }

        }

        // Return the file with PHP
        $this->setNoOutput(true);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        header('Content-Length: ' . filesize($fileName));

        // Disable any buffering to prevent OOM errors.
        ob_end_flush();
        readfile($fileName);
        exit;
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

        // Get the twig file and required data of the report form
        $form =  $object->getReportForm();

        // Show the twig
        $this->getState()->template = $form['template'];
        $this->getState()->setData([
            'reportName' => $reportName,
            'defaults' => $form['data']
        ]);
    }

    /**
     * Displays Ad Hoc Report data in charts
     */
    public function getReportData($reportName)
    {
        $this->getLog()->debug('Get report name: '.$reportName);

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