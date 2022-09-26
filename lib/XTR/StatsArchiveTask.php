<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

use Carbon\Carbon;
use Xibo\Entity\User;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Exception\TaskRunException;

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

    /** @var Carbon */
    private $lastArchiveDate = null;

    /** @var  \Xibo\Helper\SanitizerService */
    private $sanitizerService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->userFactory = $container->get('userFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->sanitizerService = $container->get('sanitizerService');
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
        $this->log->debug('Archive Stats');

        $this->runMessage = '# ' . __('Stats Archive') . PHP_EOL . PHP_EOL;

        if ($this->getOption('archiveStats', 'Off') == 'On') {
            $this->log->debug('Archive Enabled');

            // Archive tasks by week.
            $periodSizeInDays = $this->getOption('periodSizeInDays', 7);
            $maxPeriods = $this->getOption('maxPeriods', 4);
            $periodsToKeep = $this->getOption('periodsToKeep', 1);
            $this->setArchiveOwner();

            // Get the earliest
            $earliestDate = $this->timeSeriesStore->getEarliestDate();

            if ($earliestDate === null) {
                $this->log->debug('Earliest date is null, nothing to archive.');

                $this->runMessage = __('Nothing to archive');
                return;
            }

            // Wind back to the start of the day
            $earliestDate = $earliestDate->copy()->setTime(0, 0, 0);

            // Take the earliest date and roll forward until the current time
            $now = Carbon::now()->subDays($periodSizeInDays * $periodsToKeep)->setTime(0, 0, 0);
            $i = 0;

            while ($earliestDate < $now && $i < $maxPeriods) {
                $i++;

                // Push forward
                $fromDt = $earliestDate->copy();
                $earliestDate->addDays($periodSizeInDays);

                $this->log->debug('Running archive number ' . $i
                    . 'for ' . $fromDt->toAtomString() . ' - ' . $earliestDate->toAtomString());

                try {
                    $this->exportStatsToLibrary($fromDt, $earliestDate);
                } catch (\Exception $exception) {
                    $this->log->error('Export error for Archive Number ' . $i . ', e = ' . $exception->getMessage());

                    // Throw out to the task handler to record the error.
                    throw $exception;
                }

                $this->store->commitIfNecessary();

                $this->log->debug('Export success for Archive Number ' . $i);

                // Grab the last from date for use in tidy stats
                $this->lastArchiveDate = $fromDt;
            }

            $this->runMessage .= ' - ' . __('Done') . PHP_EOL . PHP_EOL;
        } else {
            $this->log->debug('Archive not enabled');
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }

        $this->log->debug('Finished archive stats, last archive date is '
            . ($this->lastArchiveDate == null ? 'null' : $this->lastArchiveDate->toAtomString()));
    }

    /**
     * Export stats to the library
     * @param Carbon $fromDt
     * @param Carbon $toDt
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function exportStatsToLibrary($fromDt, $toDt)
    {
        $this->log->debug('Export period: ' . $fromDt->toAtomString() . ' - ' . $toDt->toAtomString());
        $this->runMessage .= ' - ' . $fromDt->format(DateFormatHelper::getSystemFormat()) . ' / ' . $toDt->format(DateFormatHelper::getSystemFormat()) . PHP_EOL;

        $resultSet = $this->timeSeriesStore->getStats([
            'fromDt'=> $fromDt,
            'toDt'=> $toDt,
        ]);

        $this->log->debug('Get stats');

        // Create a temporary file for this
        $fileName = tempnam(sys_get_temp_dir(), 'stats');

        $out = fopen($fileName, 'w');
        fputcsv($out, ['Stat Date', 'Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag', 'Duration', 'Count', 'DisplayId', 'LayoutId', 'WidgetId', 'MediaId', 'Engagements']);

        $hasStatsToArchive = false;
        while ($row = $resultSet->getNextRow()) {
            $hasStatsToArchive = true;
            $sanitizedRow = $this->getSanitizer($row);

            if ($this->timeSeriesStore->getEngine() == 'mongodb') {
                $statDate = isset($row['statDate']) ? Carbon::createFromTimestamp($row['statDate']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()) : null;
                $start = Carbon::createFromTimestamp($row['start']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
                $end = Carbon::createFromTimestamp($row['end']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
                $engagements = isset($row['engagements']) ? json_encode($row['engagements']) : '[]';
            } else {
                $statDate = isset($row['statDate']) ? Carbon::createFromTimestamp($row['statDate'])->format(DateFormatHelper::getSystemFormat()) : null;
                $start = Carbon::createFromTimestamp($row['start'])->format(DateFormatHelper::getSystemFormat());
                $end = Carbon::createFromTimestamp($row['end'])->format(DateFormatHelper::getSystemFormat());
                $engagements = isset($row['engagements']) ? $row['engagements'] : '[]';
            }

            // Read the columns
            fputcsv($out, [
                $statDate,
                $sanitizedRow->getString('type'),
                $start,
                $end,
                isset($row['layout']) ? $sanitizedRow->getString('layout') :'',
                isset($row['display']) ? $sanitizedRow->getString('display') :'',
                isset($row['media']) ? $sanitizedRow->getString('media') :'',
                isset($row['tag']) ? $sanitizedRow->getString('tag') :'',
                $sanitizedRow->getInt('duration'),
                $sanitizedRow->getInt('count'),
                $sanitizedRow->getInt('displayId'),
                isset($row['layoutId']) ? $sanitizedRow->getInt('layoutId') :'',
                isset($row['widgetId']) ? $sanitizedRow->getInt('widgetId') :'',
                isset($row['mediaId']) ? $sanitizedRow->getInt('mediaId') :'',
                $engagements
            ]);
        }

        fclose($out);

        if ($hasStatsToArchive) {
            $this->log->debug('Temporary file written, zipping');

            // Create a ZIP file and add our temporary file
            $zipName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/stats.csv.zip';
            $zip = new \ZipArchive();
            $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
            }

            $zip->addFile($fileName, 'stats.csv');
            $zip->close();

            $this->log->debug('Zipped to ' . $zipName);

            // This all might have taken a long time indeed, so lets see if we need to reconnect MySQL
            $this->store->select('SELECT 1', [], 'default', true);

            $this->log->debug('MySQL connection refreshed if necessary');

            // Upload to the library
            $media = $this->mediaFactory->create(
                __('Stats Export %s to %s - %s', $fromDt->format('Y-m-d'), $toDt->format('Y-m-d'), Random::generateString(5)),
                'stats.csv.zip',
                'genericfile',
                $this->archiveOwner->getId()
            );
            $media->save();

            $this->log->debug('Media saved as ' . $media->name);

            // Commit before the delete (the delete might take a long time)
            $this->store->commitIfNecessary();

            // Set max attempts to -1 so that we continue deleting until we've removed all of the stats that we've exported
            $options = [
                'maxAttempts' => -1,
                'statsDeleteSleep' => 1,
                'limit' => 1000
            ];

            $this->log->debug('Delete stats for period: ' . $fromDt->toAtomString() . ' - ' . $toDt->toAtomString());

            // Delete the stats, incrementally
            $this->timeSeriesStore->deleteStats($toDt, $fromDt, $options);

            // This all might have taken a long time indeed, so lets see if we need to reconnect MySQL
            $this->store->select('SELECT 1', [], 'default', true);

            $this->log->debug('MySQL connection refreshed if necessary');

            $this->log->debug('Delete stats completed, export period completed.');
        } else {
            $this->log->debug('There are no stats to archive');
        }

        // Remove the CSV file
        unlink($fileName);
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

            if (count($admins) <= 0) {
                throw new TaskRunException(__('No super admins to use as the archive owner, please set one in the configuration.'));
            }

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
        $this->log->debug('Tidy stats');

        $this->runMessage .= '## ' . __('Tidy Stats') . PHP_EOL;

        $maxAge = intval($this->config->getSetting('MAINTENANCE_STAT_MAXAGE'));

        if ($maxAge != 0) {
            $this->log->debug('Max Age is ' . $maxAge);

            // Set the max age to maxAgeDays from now, or if we've archived, from the archive date
            $maxAgeDate = ($this->lastArchiveDate === null)
                ? Carbon::now()->subDays($maxAge)
                : $this->lastArchiveDate;

            // Control the flow of the deletion
            $options = [
                'maxAttempts' => $this->getOption('statsDeleteMaxAttempts', 10),
                'statsDeleteSleep' => $this->getOption('statsDeleteSleep', 3),
                'limit' => $this->getOption('limit', 10000) // Note: for mongo we dont use $options['limit'] anymore
            ];

            try {
                $this->log->debug('Calling delete stats with max age: ' . $maxAgeDate->toAtomString());

                $countDeleted = $this->timeSeriesStore->deleteStats($maxAgeDate, null, $options);

                $this->log->debug('Delete Stats complete');

                $this->runMessage .= ' - ' . sprintf(__('Done - %d deleted.'), $countDeleted) . PHP_EOL . PHP_EOL;
            } catch (\Exception $exception) {
                $this->log->error('Unexpected error running stats tidy. e = ' . $exception->getMessage());
                $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
            }
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }

        $this->log->debug('Tidy stats complete');
    }
}
