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

namespace Xibo\XTR;
use Xibo\Controller\Library;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Slim\View;
use Slim\Slim;


/**
 * Class ReportScheduleTask
 * @package Xibo\XTR
 */
class ReportScheduleTask implements TaskInterface
{
    use TaskTrait;

    /** @var View */
    private $view;

    /** @var DateServiceInterface */
    private $date;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var SavedReportFactory */
    private $savedReportFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var ReportScheduleFactory */
    private $reportScheduleFactory;

    /** @var ReportServiceInterface */
    private $reportService;

    /** @var NotificationFactory */
    private $notificationFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->view = $container->get('view');
        $this->date = $container->get('dateService');
        $this->userFactory = $container->get('userFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->savedReportFactory = $container->get('savedReportFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->reportScheduleFactory = $container->get('reportScheduleFactory');
        $this->reportService = $container->get('reportService');
        $this->notificationFactory = $container->get('notificationFactory');

        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Report schedule') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        $this->runReportSchedule();
    }

    /**
     * Run report schedule
     */
    private function runReportSchedule()
    {

        // Make sure the library exists
        Library::ensureLibraryExists($this->config->getSetting('LIBRARY_LOCATION'));

        $reportSchedules = $this->reportScheduleFactory->query(null, ['isActive' => 1]);

        // Get list of ReportSchedule
        foreach($reportSchedules as $reportSchedule) {

            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);
            $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $reportSchedule->lastRunDt))->format('U');
            $now = time();

            if ($nextRunDt <= $now) {

                // random run of report schedules
                $skip = $this->skipReportRun($now, $nextRunDt);
                if ($skip == true) {
                    continue;
                }

                // execute the report
                $rs = $this->reportScheduleFactory->getById($reportSchedule->reportScheduleId);
                $rs->previousRunDt = $rs->lastRunDt;
                $rs->lastRunDt = time();

                $this->log->debug('Last run date is updated to '. $rs->lastRunDt);

                try {
                    // Get the generated saved as report name
                    $saveAs = $this->reportService->generateSavedReportName($reportSchedule->reportName, $reportSchedule->filterCriteria);

                    // Run the report to get results
                    $result =  $this->reportService->runReport($reportSchedule->reportName, $reportSchedule->filterCriteria, $reportSchedule->userId);
                    $this->log->debug(__('Run report results: %s.', json_encode($result, JSON_PRETTY_PRINT)));

                    //  Save the result in a json file
                    $fileName = tempnam($this->config->getSetting('LIBRARY_LOCATION') . '/temp/','reportschedule');
                    $out = fopen($fileName, 'w');
                    fwrite($out, json_encode($result));
                    fclose($out);

                    // Create a ZIP file and add our temporary file
                    $zipName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/reportschedule.json.zip';
                    $zip = new \ZipArchive();
                    $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                    if ($result !== true) {
                        throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
                    }

                    $zip->addFile($fileName, 'reportschedule.json');
                    $zip->close();

                    // Remove the JSON file
                    unlink($fileName);

                    $runDateTimestamp = $this->date->parse()->format('U');

                    // Upload to the library
                    $media = $this->mediaFactory->create(__('reportschedule_' . $reportSchedule->reportScheduleId . '_' . $runDateTimestamp ), 'reportschedule.json.zip', 'savedreport', $reportSchedule->userId);
                    $media->save();

                    // Save Saved report
                    $savedReport = $this->savedReportFactory->create($saveAs, $reportSchedule->reportScheduleId, $media->mediaId, time(), $reportSchedule->userId);
                    $savedReport->save();

                    $this->createPdfAndNotification($reportSchedule, $savedReport, $media);

                    // Add the last savedreport in Report Schedule
                    $this->log->debug('Last savedReportId in Report Schedule: '. $savedReport->savedReportId);
                    $rs->lastSavedReportId = $savedReport->savedReportId;
                    $rs->message = null;

                } catch (\Exception $error) {
                    $rs->isActive = 0;
                    $rs->message = $error->getMessage();
                    $this->log->error('Error: ' . $error->getMessage());
                }

                // Finally save schedule report
                $rs->save();
            }
        }
    }

    /**
     * Create the PDF and save a notification
     * @param $reportSchedule
     * @param $savedReport
     * @param $media
     */
    private function createPdfAndNotification($reportSchedule, $savedReport, $media)
    {
        $savedReportData = $this->reportService->getSavedReportResults($savedReport->savedReportId, $reportSchedule->reportName);

        // Get the report config
        $report = $this->reportService->getReportByName($reportSchedule->reportName);

        if ($report->output_type == 'chart') {

            $quickChartUrl = $this->config->getSetting('QUICK_CHART_URL');
            if (!empty($quickChartUrl)) {
                $script = $this->reportService->getReportChartScript($savedReport->savedReportId, $reportSchedule->reportName);
                $src = $quickChartUrl. "/chart?width=1000&height=300&c=".$script;
            } else {
                $placeholder = __('Chart could not be drawn because the CMS has not been configured with a Quick Chart URL.');
            }

        } else { // only for tablebased report

            $result = $savedReportData['chartData']['result'];
            $tableData =json_decode($result, true);
        }

        // Get report email template
        $emailTemplate = $this->reportService->getReportEmailTemplate($reportSchedule->reportName);

        if(!empty($emailTemplate)) {

            // Save PDF attachment
            ob_start();
            $this->view->display($emailTemplate,
                [
                    'header' => $report->description,
                    'logo' => $this->config->uri('img/xibologo.png', true),
                    'title' => $savedReport->saveAs,
                    'periodStart' => $savedReportData['chartData']['periodStart'],
                    'periodEnd' => $savedReportData['chartData']['periodEnd'],
                    'generatedOn' => $this->date->parse($savedReport->generatedOn, 'U')->format('Y-m-d H:i:s'),
                    'tableData' => isset($tableData) ? $tableData : null,
                    'src' => isset($src) ? $src : null,
                    'placeholder' => isset($placeholder) ? $placeholder : null
                ]);
            $body = ob_get_contents();
            ob_end_clean();

            try {
                $mpdf = new \Mpdf\Mpdf([
                    'tempDir' => $this->config->getSetting('LIBRARY_LOCATION') . '/temp',
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
                $stylesheet =  file_get_contents($this->config->uri('css/email-report.css', true));
                $mpdf->WriteHTML($stylesheet, 1);
                $mpdf->WriteHTML($body);
                $mpdf->Output($this->config->getSetting('LIBRARY_LOCATION'). 'attachment/filename-'.$media->mediaId.'.pdf', \Mpdf\Output\Destination::FILE);

                // Create email notification with attachment
                $filters = json_decode($reportSchedule->filterCriteria, true);
                $sendEmail = isset($filters['sendEmail']) ? $filters['sendEmail'] : null;
                $nonusers = isset($filters['nonusers']) ? $filters['nonusers'] : null;
                if ($sendEmail) {
                    $notification = $this->notificationFactory->createEmpty();
                    $notification->subject = $report->description;
                    $notification->body = __('Attached please find the report for %s', $savedReport->saveAs);
                    $notification->createdDt = $this->date->getLocalDate(null, 'U');
                    $notification->releaseDt = time();
                    $notification->isEmail = 1;
                    $notification->isInterrupt = 0;
                    $notification->userId = $savedReport->userId; // event owner
                    $notification->filename = 'filename-'.$media->mediaId.'.pdf';
                    $notification->originalFileName = 'saved_report.pdf';
                    $notification->nonusers = $nonusers;

                    // Get user group to create user notification
                    $notificationUser = $this->userFactory->getById($savedReport->userId);
                    $notification->assignUserGroup($this->userGroupFactory->getById($notificationUser->groupId));

                    $notification->save();
                }
            } catch (\Exception $error) {
                $this->log->error($error->getMessage());
                $this->runMessage .= $error->getMessage() . PHP_EOL . PHP_EOL;
            }
        }

    }

    private function skipReportRun($now, $nextRunDt)
    {
        $fourHoursInSeconds = 4 * 3600;
        $threeHoursInSeconds = 3 * 3600;
        $twoHoursInSeconds = 2 * 3600;
        $oneHourInSeconds = 1 * 3600;

        $diffFromNow = $now - $nextRunDt;

        $range = 100;
        $random = rand(1, $range);
        if ($diffFromNow < $oneHourInSeconds) {

            // don't run the report
            if ($random <= 70 ) { // 70% chance of skipping
                return true;
            }
        } elseif ($diffFromNow < $twoHoursInSeconds) {

            // don't run the report
            if ($random <= 50 ) { // 50% chance of skipping
                return true;
            }
        } elseif ($diffFromNow < $threeHoursInSeconds) {

            // don't run the report
            if ($random <= 40 ) { // 40% chance of skipping
                return true;
            }
        } elseif ($diffFromNow < $fourHoursInSeconds) {

            // don't run the report
            if ($random <= 25 ) { // 25% chance of skipping
                return true;
            }
        }

        return false;
    }
}