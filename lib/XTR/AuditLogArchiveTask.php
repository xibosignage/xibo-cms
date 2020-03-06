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
use Jenssegers\Date\Date;
use Xibo\Entity\User;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
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
        // Archive tasks by week.
        $maxPeriods = $this->getOption('maxPeriods', 1);
        $this->setArchiveOwner();

        // Delete or Archive
        if ($this->getOption('deleteInstead', 1) == 1) {
            $this->runMessage = '# ' . __('AuditLog Delete') . PHP_EOL . PHP_EOL;

            // Delete all audit log messages older than 1 month
            $this->store->update('DELETE FROM `auditlog` WHERE logDate < :logDate', [
                'logDate' => $this->date->parse()->subMonth()->setTime(0, 0, 0)->format('U')
            ]);

        } else {

            $this->runMessage = '# ' . __('AuditLog Archive') . PHP_EOL . PHP_EOL;


            // Get the earliest
            $earliestDate = $this->store->select('SELECT MIN(logDate) AS minDate FROM `auditlog`', []);

            if (count($earliestDate) <= 0) {
                $this->runMessage = __('Nothing to archive');
                return;
            }

            /** @var Date $earliestDate */
            $earliestDate = $this->date->parse($earliestDate[0]['minDate'], 'U')->setTime(0, 0, 0);

            // Take the earliest date and roll forward until the current time
            /** @var Date $now */
            $now = $this->date->parse()->subMonth()->setTime(0, 0, 0);
            $i = 0;

            while ($earliestDate < $now && $i <= $maxPeriods) {
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
     * @param Date $fromDt
     * @param Date $toDt
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function exportAuditLogToLibrary($fromDt, $toDt)
    {
        $this->runMessage .= ' - ' . $fromDt . ' / ' . $toDt . PHP_EOL;

        $sql = '
            SELECT *
              FROM `auditlog`
             WHERE 1 = 1
              AND logDate >= :fromDt
              AND logDate < :toDt
        ';

        $params = [
            'fromDt' => $this->date->getLocalDate($fromDt, 'U'),
            'toDt' => $this->date->getLocalDate($toDt, 'U')
        ];

        $sql .= " ORDER BY 1 ";

        $records = $this->store->select($sql, $params);

        if (count($records) <= 0) {
            $this->runMessage .= __('No audit log found for these dates') . PHP_EOL;
            return;
        }

        // Create a temporary file for this
        $fileName = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/auditlog.csv';

        $out = fopen($fileName, 'w');
        fputcsv($out, ['logId', 'logDate', 'userId', 'message', 'entity', 'entityId', 'objectAfter']);

        // Do some post processing
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
        if ($result !== true)
            throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));

        $zip->addFile($fileName, 'auditlog.csv');
        $zip->close();

        // Remove the CSV file
        unlink($fileName);

        // Upload to the library
        $media = $this->mediaFactory->create(__('AuditLog Export %s to %s', $fromDt->format('Y-m-d'), $toDt->format('Y-m-d')), 'auditlog.csv.zip', 'genericfile', $this->archiveOwner->getId());
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