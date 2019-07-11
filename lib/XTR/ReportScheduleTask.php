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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\ReportServiceInterface;

/**
 * Class ReportScheduleTask
 * @package Xibo\XTR
 */
class ReportScheduleTask implements TaskInterface
{
    use TaskTrait;

    /** @var DateServiceInterface */
    private $date;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var SavedReportFactory */
    private $savedReportFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var ReportScheduleFactory */
    private $reportScheduleFactory;

    /** @var ReportServiceInterface */
    private $reportService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->date = $container->get('dateService');
        $this->userFactory = $container->get('userFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->savedReportFactory = $container->get('savedReportFactory');
        $this->reportScheduleFactory = $container->get('reportScheduleFactory');
        $this->reportService = $container->get('reportService');
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
     *
     */
    private function runReportSchedule()
    {

        $reportSchedules = $this->reportScheduleFactory->query(null, ['isActive' => 1]);
//        var_dump(count($reportSchedules));
//        die();

        // Get list of ReportSchedule
        foreach($reportSchedules as $reportSchedule) {

            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);

            $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $reportSchedule->lastRunDt))->format('U');

            if ($nextRunDt <= time())
            {

                $rs = $this->reportScheduleFactory->getById($reportSchedule->reportScheduleId);
                $rs->previousRunDt = $rs->lastRunDt;
                $rs->lastRunDt = time();
                $rs->save();

                // Get the generated saved as report name
                $saveAs = $this->reportService->generateSavedReportName($reportSchedule->reportName, $reportSchedule->filterCriteria);

                $this->log->debug('Last run date is updated to '. $rs->lastRunDt);

                // Run the report to get results
                $result =  $this->reportService->runReport($reportSchedule->reportName, $reportSchedule->filterCriteria);
                $this->log->debug('Run report results: %s.', json_encode($result, JSON_PRETTY_PRINT));

                //  Save the result in a json file
                $fileName = tempnam(sys_get_temp_dir(), 'reportschedule');
                $out = fopen($fileName, 'w');
                fwrite($out, json_encode($result));
                fclose($out);

                // Create a ZIP file and add our temporary file
                $zipName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/reportschedule.json.zip';
                $zip = new \ZipArchive();
                $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                if ($result !== true)
                    throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));

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

                // Add the last savedreport in Report Schedule
                $this->log->debug('Last savedReportId in Report Schedule: '. $savedReport->savedReportId);
                $rs->lastSavedReportId = $savedReport->savedReportId;
                $rs->save();
            }
        }
    }
}