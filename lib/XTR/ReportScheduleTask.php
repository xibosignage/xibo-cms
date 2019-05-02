<?php

namespace Xibo\XTR;
use Xibo\Controller\Library;
use Xibo\Exception\XiboException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Entity\User;
use Xibo\Exception\TaskRunException;
use Xibo\Exception\NotFoundException;

/**
 * Class ReportScheduleTask
 * @package Xibo\XTR
 */
class ReportScheduleTask implements TaskInterface
{
    use TaskTrait;

    /** @var DateServiceInterface */
    private $date;

    /** @var  User */
    private $archiveOwner;

    /** @var MediaFactory */
    private $mediaFactory;

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
        $this->reportScheduleFactory = $container->get('reportScheduleFactory');
        $this->reportService = $container->get('reportService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Report schedule') . PHP_EOL . PHP_EOL;

        $this->setArchiveOwner();

        // Long running task
        set_time_limit(0);

        $this->runReportSchedule();
    }

    /**
     *
     */
    private function runReportSchedule()
    {
        $this->runMessage .= '## ' . __('Run report schedule') . PHP_EOL;

        $reportSchedules = $this->reportScheduleFactory->query();

        // Get list of ReportSchedule
        foreach($reportSchedules as $reportSchedule) {

            $cron = \Cron\CronExpression::factory($reportSchedule->schedule);

            $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $reportSchedule->lastRunDt))->format('U');

            //if ($nextRunDt <= time())
            {

                $rs = $this->reportScheduleFactory->getById($reportSchedule->reportScheduleId);
                $rs->lastRunDt = time();
                $rs->save();

                // Run the report to get results
                $result =  $this->reportService->runReport($reportSchedule->reportName, $reportSchedule->filterCriteria);

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

                $runDateTimestamp = $this->date->parse()->addDay()->startOfDay()->format('U');

                // Upload to the library
                $media = $this->mediaFactory->create(__('reportschedule_' . $reportSchedule->reportScheduleId . '_' . $runDateTimestamp ), 'reportschedule.json.zip', 'xibosavedreport', $this->archiveOwner->getId());
                $media->save();

            }


        }
    }

    /**
     * Set the archive owner
     * @throws TaskRunException
     */
    private function setArchiveOwner()
    {
        $archiveOwner = $this->getOption('archiveOwner', null);

        if ($archiveOwner == null) {
            $admins = $this->userFactory->getSuperAdmins();

            if (count($admins) <= 0)
                throw new TaskRunException(__('No super admins to use as the archive owner, please set one in the configuration.'));

            $this->archiveOwner = $admins[0];

        } else {
            try {
                $this->archiveOwner = $this->userFactory->getByName($archiveOwner);
            } catch (NotFoundException $e) {
                throw new TaskRunException(__('Archive Owner not found'));
            }
        }
    }
}