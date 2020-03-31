<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

            /** @var Carbon $earliestDate */
            $earliestDate = Carbon::createFromTimestamp($earliestDate['minDate'])->setTime(0, 0, 0);

            // Take the earliest date and roll forward until the current time
            /** @var Carbon $now */
            $now = Carbon::now()->subDays($periodSizeInDays * $periodsToKeep)->setTime(0, 0, 0);
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
     * @param Carbon $fromDt
     * @param Carbon $toDt
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function exportStatsToLibrary($fromDt, $toDt)
    {
        $this->runMessage .= ' - ' . $fromDt->format(DateFormatHelper::getSystemFormat()) . ' / ' . $toDt->format(DateFormatHelper::getSystemFormat()) . PHP_EOL;

        $resultSet = $this->timeSeriesStore->getStats([
            'fromDt'=> $fromDt,
            'toDt'=> $toDt,
        ]);

        // Create a temporary file for this
        $fileName = tempnam(sys_get_temp_dir(), 'stats');

        $out = fopen($fileName, 'w');
        fputcsv($out, ['Stat Date', 'Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag', 'Duration', 'Count', 'DisplayId', 'LayoutId', 'WidgetId', 'MediaId']);

        while ($row = $resultSet->getNextRow() ) {

            $sanitizedRow = $this->getSanitizer($row);

            if ($this->timeSeriesStore->getEngine() == 'mongodb') {

                $statDate = isset($row['statDate']) ? Carbon::createFromTimestamp($row['statDate']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()) : null;
                $start = Carbon::createFromTimestamp($row['start']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
                $end = Carbon::createFromTimestamp($row['end']->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat());
            } else {

                $statDate = isset($row['statDate']) ? Carbon::createFromTimestamp($row['statDate'])->format(DateFormatHelper::getSystemFormat()) : null;
                $start = Carbon::createFromTimestamp($row['start'])->format(DateFormatHelper::getSystemFormat());
                $end = Carbon::createFromTimestamp($row['end'])->format(DateFormatHelper::getSystemFormat());
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
                $sanitizedRow->getString('duration'),
                $sanitizedRow->getString('count'),
                $sanitizedRow->getInt('displayId'),
                isset($row['layoutId']) ? $sanitizedRow->getInt('layoutId') :'',
                isset($row['widgetId']) ? $sanitizedRow->getInt('widgetId') :'',
                isset($row['mediaId']) ? $sanitizedRow->getInt('mediaId') :'',
            ]);
        }

        fclose($out);

        // Create a ZIP file and add our temporary file
        $zipName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/stats.csv.zip';
        $zip = new \ZipArchive();
        $result = $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
        }

        $zip->addFile($fileName, 'stats.csv');
        $zip->close();

        // Remove the CSV file
        unlink($fileName);

        // Upload to the library
        $media = $this->mediaFactory->create(__('Stats Export %s to %s - ' . Random::generateString(5), $fromDt->format('Y-m-d'), $toDt->format('Y-m-d')), 'stats.csv.zip', 'genericfile', $this->archiveOwner->getId());
        $media->save();

        // Set max attempts to -1 so that we continue deleting until we've removed all of the stats that we've exported
        $options = [
            'maxAttempts' => -1,
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

            $maxage = Carbon::now()->subDays(intval($this->config->getSetting('MAINTENANCE_STAT_MAXAGE')));
            $maxAttempts = $this->getOption('statsDeleteMaxAttempts', 10);
            $statsDeleteSleep = $this->getOption('statsDeleteSleep', 3);

            $options = [
                'maxAttempts' => $maxAttempts,
                'statsDeleteSleep' => $statsDeleteSleep,
                'limit' => 10000 // Note: for mongo we dont use $options['limit'] anymore
            ];

            try {
                $result = $this->timeSeriesStore->deleteStats($maxage, null, $options);
                if ($result > 0) {
                    $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
                }
            } catch (\Exception $exception) {
                $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
            }
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }
}