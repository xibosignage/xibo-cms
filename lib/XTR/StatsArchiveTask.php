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

/**
 * Class StatsArchiveTask
 * @package Xibo\XTR
 */
class StatsArchiveTask implements TaskInterface
{
    use TaskTrait;

    /** @var  User */
    private $archiveOwner;

    /** @inheritdoc */
    public function run()
    {
        // Archive tasks by week.
        $periodSizeInDays = $this->getOption('periodSizeInDays', 7);
        $maxPeriods = $this->getOption('maxPeriods', 4);
        $periodsToKeep = $this->getOption('periodsToKeep', 1);
        $this->setArchiveOwner();

        $this->runMessage = '# ' . __('Stats Archive') . PHP_EOL . PHP_EOL;

        // Get the earliest
        $earliestDate = $this->store->select('SELECT MIN(statDate) AS minDate FROM `stat`', []);

        if (count($earliestDate) <= 0) {
            $this->runMessage = __('Nothing to archive');
            return;
        }

        /** @var Date $earliestDate */
        $earliestDate = $this->date->parse($earliestDate[0]['minDate'])->setTime(0, 0, 0);

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

        $this->runMessage .= __('Done') . PHP_EOL . PHP_EOL;
    }

    /**
     * Export stats to the library
     * @param Date $fromDt
     * @param Date $toDt
     */
    private function exportStatsToLibrary($fromDt, $toDt)
    {
        $this->runMessage .= ' - ' . $fromDt . ' / ' . $toDt . PHP_EOL;

        $select = 'SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName';
        $countSelect = 'SELECT COUNT(*) AS cnt';

        $sql = '
              FROM stat
                INNER JOIN display
                ON stat.DisplayID = display.DisplayID
                LEFT OUTER JOIN layout
                ON layout.LayoutID = stat.LayoutID
                LEFT OUTER JOIN media
                ON media.mediaID = stat.mediaID
             WHERE 1 = 1
              AND stat.statDate >= :fromDt
              AND stat.statDate < :toDt
             ORDER BY stat.statDate
        ';

        $params = [
            'fromDt' => $this->date->getLocalDate($fromDt),
            'toDt' => $this->date->getLocalDate($toDt)
        ];

        // How many records are we expecting?
        $records = $this->store->select($countSelect . $sql, $params);

        if (count($records) <= 0)
            return;

        $records = $this->sanitizer->int($records[0]['cnt']);

        // Create a temporary file for this
        $fileName = tempnam(sys_get_temp_dir(), 'stats');

        $out = fopen($fileName, 'w');
        fputcsv($out, ['Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag', 'DisplayId', 'LayoutId', 'WidgetId', 'MediaId']);

        // Get records in blocks of 1000
        $i = 0;
        while ($i < $records) {

            $rows = $this->store->select($select . $sql . ' LIMIT ' . $i . ', 1000 ', $params);

            // Do some post processing
            foreach ($rows as $row) {
                // Read the columns
                fputcsv($out, [
                    $this->sanitizer->string($row['Type']),
                    $this->sanitizer->string($row['start']),
                    $this->sanitizer->string($row['end']),
                    $this->sanitizer->string($row['Layout']),
                    $this->sanitizer->string($row['Display']),
                    $this->sanitizer->string($row['MediaName']),
                    $this->sanitizer->string($row['Tag']),
                    $this->sanitizer->int($row['displayID']),
                    $this->sanitizer->int($row['layoutID']),
                    $this->sanitizer->int($row['widgetId']),
                    $this->sanitizer->int($row['mediaID'])
                ]);
            }

            $i = $i + 1000;
        }

        fclose($out);

        // Create a ZIP file and add our temporary file
        $zipName = $this->config->GetSetting('LIBRARY_LOCATION') . 'temp/stats.csv.zip';
        $zip = new \ZipArchive();
        $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true)
            throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));

        $zip->addFile($fileName, 'stats.csv');
        $zip->close();

        // Remove the CSV file
        unlink($fileName);

        // Upload to the library
        $media = $this->mediaFactory->create(__('Stats Export %s to %s', $fromDt->format('Y-m-d'), $toDt->format('Y-m-d')), 'stats.csv.zip', 'genericfile', $this->archiveOwner->getId());
        $media->save();

        // Delete the stats
        $this->store->update('DELETE FROM `stat` WHERE stat.statDate >= :fromDt AND stat.statDate < :toDt', $params);
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