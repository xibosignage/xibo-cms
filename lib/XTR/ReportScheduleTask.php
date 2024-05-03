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

namespace Xibo\XTR;

use Carbon\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Slim\Views\Twig;
use Xibo\Entity\ReportResult;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\MediaService;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class ReportScheduleTask
 * @package Xibo\XTR
 */
class ReportScheduleTask implements TaskInterface
{
    use TaskTrait;

    /** @var Twig */
    private $view;

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
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function runReportSchedule()
    {

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);
        $reportSchedules = $this->reportScheduleFactory->query(null, ['isActive' => 1, 'disableUserCheck' => 1]);

        // Get list of ReportSchedule
        foreach ($reportSchedules as $reportSchedule) {
            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);
            $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $reportSchedule->lastRunDt))->format('U');
            $now = Carbon::now()->format('U');

            // if report start date is greater than now
            // then dont run the report schedule
            if ($reportSchedule->fromDt > $now) {
                $this->log->debug('Report schedule start date is in future '. $reportSchedule->fromDt);
                continue;
            }

            // if report end date is less than or equal to now
            // then disable report schedule
            if ($reportSchedule->toDt != 0 && $reportSchedule->toDt <= $now) {
                $reportSchedule->message = 'Report schedule end date has passed';
                $reportSchedule->isActive = 0;
            }

            if ($nextRunDt <= $now  && $reportSchedule->isActive) {
                // random run of report schedules
                $skip = $this->skipReportRun($now, $nextRunDt);
                if ($skip == true) {
                    continue;
                }
                // execute the report
                $reportSchedule->previousRunDt = $reportSchedule->lastRunDt;
                $reportSchedule->lastRunDt = Carbon::now()->format('U');

                $this->log->debug('Last run date is updated to '. $reportSchedule->lastRunDt);

                try {
                    // Get the generated saved as report name
                    $saveAs = $this->reportService->generateSavedReportName(
                        $reportSchedule->reportName,
                        $reportSchedule->filterCriteria
                    );

                    // Run the report to get results
                    // pass in the user who saved the report
                    $result = $this->reportService->runReport(
                        $reportSchedule->reportName,
                        $reportSchedule->filterCriteria,
                        $this->userFactory->getById($reportSchedule->userId)
                    );

                    $this->log->debug(__('Run report results: %s.', json_encode($result, JSON_PRETTY_PRINT)));

                    //  Save the result in a json file
                    $fileName = tempnam($this->config->getSetting('LIBRARY_LOCATION') . '/temp/', 'reportschedule');
                    $out = fopen($fileName, 'w');
                    fwrite($out, json_encode($result));
                    fclose($out);

                    $savedReportFileName = 'rs_'.$reportSchedule->reportScheduleId. '_'. Carbon::now()->format('U');

                    // Create a ZIP file and add our temporary file
                    $zipName = $this->config->getSetting('LIBRARY_LOCATION') . 'savedreport/'.$savedReportFileName.'.zip';
                    $zip = new \ZipArchive();
                    $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                    if ($result !== true) {
                        throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
                    }

                    $zip->addFile($fileName, 'reportschedule.json');
                    $zip->close();

                    // Remove the JSON file
                    unlink($fileName);
                    // Save Saved report
                    $savedReport = $this->savedReportFactory->create(
                        $saveAs,
                        $reportSchedule->reportScheduleId,
                        Carbon::now()->format('U'),
                        $reportSchedule->userId,
                        $savedReportFileName.'.zip',
                        filesize($zipName),
                        md5_file($zipName)
                    );
                    $savedReport->save();

                    $this->createPdfAndNotification($reportSchedule, $savedReport);

                    // Add the last savedreport in Report Schedule
                    $this->log->debug('Last savedReportId in Report Schedule: '. $savedReport->savedReportId);
                    $reportSchedule->lastSavedReportId = $savedReport->savedReportId;
                    $reportSchedule->message = null;
                } catch (\Exception $error) {
                    $reportSchedule->isActive = 0;
                    $reportSchedule->message = $error->getMessage();
                    $this->log->error('Error: ' . $error->getMessage());
                }
            }

            // Finally save schedule report
            $reportSchedule->save();
        }
    }

    /**
     * Create the PDF and save a notification
     * @param $reportSchedule
     * @param $savedReport
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function createPdfAndNotification($reportSchedule, $savedReport)
    {
        /* @var ReportResult $savedReportData */
        $savedReportData = $this->reportService->getSavedReportResults(
            $savedReport->savedReportId,
            $reportSchedule->reportName
        );

        // Get the report config
        $report = $this->reportService->getReportByName($reportSchedule->reportName);

        if ($report->output_type == 'both' || $report->output_type == 'chart') {
            $quickChartUrl = $this->config->getSetting('QUICK_CHART_URL');
            if (!empty($quickChartUrl)) {
                $quickChartUrl .= '/chart?width=1000&height=300&c=';

                $chartScript = $this->reportService->getReportChartScript(
                    $savedReport->savedReportId,
                    $reportSchedule->reportName
                );

                // Replace " with ' for the quick chart URL
                $src = $quickChartUrl . str_replace('"', '\'', $chartScript);

                // If multiple charts needs to be displayed
                $multipleCharts = [];
                $chartScriptArray = json_decode($chartScript, true);
                foreach ($chartScriptArray as $key => $chartData) {
                    $multipleCharts[$key] = $quickChartUrl . str_replace('"', '\'', json_encode($chartData));
                }
            } else {
                $placeholder = __('Chart could not be drawn because the CMS has not been configured with a Quick Chart URL.');
            }
        }

        if ($report->output_type == 'both' || $report->output_type == 'table') {
            $tableData = $savedReportData->table;
        }

        // Get report email template
        $emailTemplate = $this->reportService->getReportEmailTemplate($reportSchedule->reportName);

        if (!empty($emailTemplate)) {
            // Save PDF attachment
            ob_start();
            echo $this->view->fetch(
                $emailTemplate,
                [
                    'header' => $report->description,
                    'logo' => $this->config->uri('img/xibologo.png', true),
                    'title' => $savedReport->saveAs,
                    'metadata' => $savedReportData->metadata,
                    'tableData' => $tableData ?? null,
                    'src' => $src ?? null,
                    'multipleCharts' => $multipleCharts ?? null,
                    'placeholder' => $placeholder ?? null
                ]
            );
            $body = ob_get_contents();
            ob_end_clean();

            try {
                $mpdf = new Mpdf([
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
                $mpdf->Output(
                    $this->config->getSetting('LIBRARY_LOCATION') . 'attachment/filename-'.$savedReport->savedReportId.'.pdf',
                    Destination::FILE
                );

                // Create email notification with attachment
                $filters = json_decode($reportSchedule->filterCriteria, true);
                $sendEmail = $filters['sendEmail'] ?? null;
                $nonusers = $filters['nonusers'] ?? null;
                if ($sendEmail) {
                    $notification = $this->notificationFactory->createEmpty();
                    $notification->subject = $report->description;
                    $notification->body = __('Attached please find the report for %s', $savedReport->saveAs);
                    $notification->createDt = Carbon::now()->format('U');
                    $notification->releaseDt = Carbon::now()->format('U');
                    $notification->isInterrupt = 0;
                    $notification->userId = $savedReport->userId; // event owner
                    $notification->filename = 'filename-'.$savedReport->savedReportId.'.pdf';
                    $notification->originalFileName = 'saved_report.pdf';
                    $notification->nonusers = $nonusers;
                    $notification->type = 'report';

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
            if ($random <= 70) { // 70% chance of skipping
                return true;
            }
        } elseif ($diffFromNow < $twoHoursInSeconds) {
            // don't run the report
            if ($random <= 50) { // 50% chance of skipping
                return true;
            }
        } elseif ($diffFromNow < $threeHoursInSeconds) {
            // don't run the report
            if ($random <= 40) { // 40% chance of skipping
                return true;
            }
        } elseif ($diffFromNow < $fourHoursInSeconds) {
            // don't run the report
            if ($random <= 25) { // 25% chance of skipping
                return true;
            }
        }

        return false;
    }
}
