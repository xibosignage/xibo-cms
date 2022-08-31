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
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Exception\TaskRunException;

/**
 * Class StatsArchiveTask
 * @package Xibo\XTR
 */
class AuditLogArchiveTask implements TaskInterface
{
    use TaskTrait;

    /** @var  User */
    private $archiveOwner;

    /** @var UserFactory */
    private $userFactory;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var  \Xibo\Helper\SanitizerService */
    private $sanitizerService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        $this->userFactory = $container->get('userFactory');
        $this->sanitizerService = $container->get('sanitizerService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $maxPeriods = intval($this->getOption('maxPeriods', 1));
        $maxAge = Carbon::now()
            ->subMonths(intval($this->getOption('maxAgeMonths', 1)))
            ->startOfDay();

        $this->setArchiveOwner();

        // Delete or Archive?
        if ($this->getOption('deleteInstead', 1) == 1) {
            $this->appendRunMessage('# ' . __('AuditLog Delete'));
            $this->appendRunMessage('maxAge: ' . $maxAge->format(DateFormatHelper::getSystemFormat()));

            // Delete all audit log messages older than the configured number of months
            $this->store->update('DELETE FROM `auditlog` WHERE logDate < :logDate', [
                'logDate' => $maxAge->format('U')
            ]);
        } else {
            $this->appendRunMessage('# ' . __('AuditLog Archive'));
            $this->appendRunMessage('maxAge: ' . $maxAge->format(DateFormatHelper::getSystemFormat()));

            // Get the earliest
            $earliestDate = $this->store->select('
                SELECT MIN(logDate) AS minDate FROM `auditlog` WHERE logDate < :logDate
            ', [
                'logDate' => $maxAge->format('U')
            ]);

            if (count($earliestDate) <= 0 || $earliestDate[0]['minDate'] === null) {
                $this->appendRunMessage(__('Nothing to archive'));
                return;
            }

            // Take the earliest date and roll forward until the max age
            $earliestDate = Carbon::createFromTimestamp($earliestDate[0]['minDate'])->startOfDay();
            $now = Carbon::now()->subMonth()->startOfDay();
            $i = 0;

            while ($earliestDate < $now && $i <= $maxPeriods) {
                // We only archive up until the max age, leaving newer records alone.
                if ($earliestDate->greaterThanOrEqualTo($maxAge)) {
                    $this->appendRunMessage(__('Exceeded max age: '
                        . $maxAge->format(DateFormatHelper::getSystemFormat())));
                    break;
                }

                $i++;

                $this->log->debug('Running archive number ' . $i);

                // Push forward
                $fromDt = $earliestDate->copy();
                $earliestDate->addMonth();

                $this->exportAuditLogToLibrary($fromDt, $earliestDate);
                $this->store->commitIfNecessary();
            }
        }

        $this->runMessage .= __('Done') . PHP_EOL . PHP_EOL;
    }

    /**
     * Export stats to the library
     * @param Carbon $fromDt
     * @param Carbon $toDt
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function exportAuditLogToLibrary($fromDt, $toDt)
    {
        $this->runMessage .= ' - ' . $fromDt . ' / ' . $toDt . PHP_EOL;

        $sql = '
            SELECT *
              FROM `auditlog`
             WHERE logDate >= :fromDt
              AND logDate < :toDt
        ';

        $params = [
            'fromDt' => $fromDt->format('U'),
            'toDt' => $toDt->format('U')
        ];

        $sql .= ' ORDER BY 1 ';

        $records = $this->store->select($sql, $params);

        if (count($records) <= 0) {
            $this->runMessage .= __('No audit log found for these dates') . PHP_EOL;
            return;
        }

        // Create a temporary file for this
        $fileName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/auditlog.csv';

        $out = fopen($fileName, 'w');
        fputcsv($out, ['logId', 'logDate', 'userId', 'message', 'entity', 'entityId', 'objectAfter']);

        // Do some post-processing
        foreach ($records as $row) {
            $sanitizedRow = $this->getSanitizer($row);
            // Read the columns
            fputcsv($out, [
                $sanitizedRow->getInt('logId'),
                $sanitizedRow->getInt('logDate'),
                $sanitizedRow->getInt('userId'),
                $sanitizedRow->getString('message'),
                $sanitizedRow->getString('entity'),
                $sanitizedRow->getInt('entityId'),
                $sanitizedRow->getString('objectAfter')
            ]);
        }

        fclose($out);

        $zip = new \ZipArchive();
        $result = $zip->open($fileName . '.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
        }

        $zip->addFile($fileName, 'auditlog.csv');
        $zip->close();

        // Remove the CSV file
        unlink($fileName);

        // Upload to the library
        $media = $this->mediaFactory->create(
            __('AuditLog Export %s to %s', $fromDt->format('Y-m-d'), $toDt->format('Y-m-d')),
            'auditlog.csv.zip',
            'genericfile',
            $this->archiveOwner->getId()
        );
        $media->save();

        // Delete the logs
        $this->store->update('DELETE FROM `auditlog` WHERE logDate >= :fromDt AND logDate < :toDt', $params);
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