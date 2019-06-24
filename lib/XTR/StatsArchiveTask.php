<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (StatsArchiveTask.php)
 */


namespace Xibo\XTR;
use Jenssegers\Date\Date;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\TaskRunException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;

/**
 * Class StatsArchiveTask
 * @package Xibo\XTR
 */
class StatsArchiveTask implements TaskInterface
{
    use TaskTrait;

    /** @var  User */
    private $archiveOwner;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->userFactory = $container->get('userFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->archiveStats();
        $this->tidyStats();
    }

    public function archiveStats()
    {
        $this->runMessage = '# ' . __('Stats Archive') . PHP_EOL . PHP_EOL;

        if ($this->getOption('archiveStats', "Off") == "On") {
            // Archive tasks by week.
            $periodSizeInDays = $this->getOption('periodSizeInDays', 7);
            $maxPeriods = $this->getOption('maxPeriods', 4);
            $periodsToKeep = $this->getOption('periodsToKeep', 1);
            $this->setArchiveOwner();

            // Get the earliest
            $earliestDate = $this->timeSeriesStore->getEarliestDate();

            if (count($earliestDate) <= 0) {
                $this->runMessage = __('Nothing to archive');
                return;
            }

            /** @var Date $earliestDate */
            $earliestDate = $this->date->parse($earliestDate[0]['minDate'], 'U')->setTime(0, 0, 0);

            // Take the earliest date and roll forward until the current time
            /** @var Date $now */
            $now = $this->date->parse()->subDay($periodSizeInDays * $periodsToKeep)->setTime(0, 0, 0);
            $i = 0;

            while ($earliestDate < $now && $i < $maxPeriods) {
                $i++;

                $this->log->debug('Running archive number ' . $i);

                // Push forward
                $fromDt = $earliestDate->copy();
                $earliestDate->addDays($periodSizeInDays);

                $this->exportStatsToLibrary($fromDt, $earliestDate);
                $this->store->commitIfNecessary();
            }

            $this->runMessage .= ' - ' . __('Done') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Export stats to the library
     * @param Date $fromDt
     * @param Date $toDt
     */
    private function exportStatsToLibrary($fromDt, $toDt)
    {
        $this->runMessage .= ' - ' . $fromDt . ' / ' . $toDt . PHP_EOL;

        /*Format param dates*/
        $fromDt = $this->date->getLocalDate($fromDt);
        $toDt = $this->date->getLocalDate($toDt);

        $resultSet = $this->timeSeriesStore->getStats($fromDt, $toDt);

        // Get results as array
        $result = $resultSet->getArray();

        // Create a temporary file for this
        $fileName = tempnam(sys_get_temp_dir(), 'stats');

        $out = fopen($fileName, 'w');
        fputcsv($out, ['Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag', 'DisplayId', 'LayoutId', 'WidgetId', 'MediaId']);

        foreach ($result['statData'] as $row) {

            // Read the columns
            fputcsv($out, [
                $this->sanitizer->string($row['type']),
                $this->sanitizer->string($row['start']),
                $this->sanitizer->string($row['end']),
                $this->sanitizer->string($row['layout']),
                $this->sanitizer->string($row['display']),
                $this->sanitizer->string($row['media']),
                $this->sanitizer->string($row['tag']),
                $this->sanitizer->int($row['displayId']),
                $this->sanitizer->int($row['layoutId']),
                $this->sanitizer->int($row['widgetId']),
                $this->sanitizer->int($row['mediaId'])
            ]);
        }

        fclose($out);

        // Create a ZIP file and add our temporary file
        $zipName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/stats.csv.zip';
        $zip = new \ZipArchive();
        $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true)
            throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));

        $zip->addFile($fileName, 'stats.csv');
        $zip->close();

        // Remove the CSV file
        unlink($fileName);

        // Upload to the library
        $media = $this->mediaFactory->create(__('Stats Export %s to %s - '.bin2hex(random_bytes(5)), $this->date->parse($fromDt)->format('Y-m-d'), $this->date->parse($toDt)->format('Y-m-d')), 'stats.csv.zip', 'genericfile', $this->archiveOwner->getId());
        $media->save();

        $options = [
            'maxAttempts' => 10,
            'statsDeleteSleep' => 1,
            'limit' => 1000
        ];
        // Delete the stats, incrementally
        $this->timeSeriesStore->deleteStats($toDt, $fromDt, $options);

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

    /**
     * Tidy Stats
     */
    private function tidyStats()
    {
        $this->runMessage .= '## ' . __('Tidy Stats') . PHP_EOL;

        if ($this->config->getSetting('MAINTENANCE_STAT_MAXAGE') != 0) {

            $maxage = date('Y-m-d H:i:s', time() - (86400 * intval($this->config->getSetting('MAINTENANCE_STAT_MAXAGE'))));

            $maxAttempts = $this->getOption('statsDeleteMaxAttempts', 10);

            $statsDeleteSleep = $this->getOption('statsDeleteSleep', 3);

            $options = [
                'maxAttempts' => $maxAttempts,
                'statsDeleteSleep' => $statsDeleteSleep,
                'limit' => 10000
            ];

            try {
                $result = $this->timeSeriesStore->deleteStats($maxage, null, $options);
                if ($result > 0) {
                    $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
                }
            } catch (\RuntimeException $exception) {
                $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
            }
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }
}