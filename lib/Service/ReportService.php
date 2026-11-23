<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Service;

use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\ReportResult;
use Xibo\Factory\SavedReportFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Report\ReportInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ReportScheduleService
 *
 * @package Xibo\Service
 */
class ReportService implements ReportServiceInterface
{
    /**
     * @var ContainerInterface
     */
    public $container;

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var TimeSeriesStoreInterface
     */
    private $timeSeriesStore;

    /**
     * @var LogServiceInterface
     */
    private $log;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var SanitizerService
     */
    private $sanitizer;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /**
     * @inheritdoc
     */
    public function __construct($container, $store, $timeSeriesStore, $log, $config, $sanitizer, $savedReportFactory)
    {
        $this->container = $container;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->log = $log;
        $this->config = $config;
        $this->sanitizer = $sanitizer;
        $this->savedReportFactory = $savedReportFactory;
    }

    /** @inheritDoc */
    public function setDispatcher(EventDispatcherInterface $dispatcher): ReportServiceInterface
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    public function getDispatcher(): EventDispatcherInterface
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new EventDispatcher();
        }
        return $this->dispatcher;
    }

    /**
     * @inheritdoc
     */
    public function listReports()
    {
        $reports = [];

        $files = glob(PROJECT_ROOT . '/reports/*.report');

        foreach ($files as $file) {
            $config = json_decode(file_get_contents($file));
            $config->file = Str::replaceFirst(PROJECT_ROOT, '', $file);

            // Compatibility check
            if (!isset($config->feature) || !isset($config->category)) {
                continue;
            }

            // Check if only allowed for admin
            if ($this->container->get('user')->userTypeId != 1) {
                if (isset($config->adminOnly) && !empty($config->adminOnly)) {
                    continue;
                }
            }

            // Check Permissions
            if (!$this->container->get('user')->featureEnabled($config->feature)) {
                continue;
            }

            $reports[$config->category][] = $config;
        }

        $this->log->debug('Reports found in total: '.count($reports));

        foreach ($reports as $k => $report) {
            usort($report, function ($a, $b) {

                if (empty($a->sort_order) || empty($b->sort_order)) {
                    return 0;
                }

                return $a->sort_order - $b->sort_order;
            });

            $reports[$k] = $report;
        }

        return $reports;
    }

    /**
     * @inheritdoc
     */
    public function getReportByName($reportName)
    {
        foreach ($this->listReports() as $reports) {
            foreach ($reports as $report) {
                if ($report->name == $reportName) {
                    $this->log->debug('Get report by name: '.json_encode($report, JSON_PRETTY_PRINT));

                    return $report;
                }
            }
        }

        //throw error
        throw new NotFoundException(__('Get Report By Name: No file to return'));
    }

    /**
     * @inheritdoc
     */
    public function getReportClass($reportName)
    {
        foreach ($this->listReports() as $reports) {
            foreach ($reports as $report) {
                if ($report->name == $reportName) {
                    if ($report->class == '') {
                        throw new NotFoundException(__('Report class not found'));
                    }
                    $this->log->debug('Get report class: '.$report->class);

                    return $report->class;
                }
            }
        }

        // throw error
        throw new NotFoundException(__('Get report class: No file to return'));
    }

    /**
     * @inheritdoc
     */
    public function createReportObject($className)
    {
        if (!\class_exists($className)) {
            throw new NotFoundException(__('Class %s not found', $className));
        }

        /** @var ReportInterface $object */
        $object = new $className();
        $object
            ->setCommonDependencies(
                $this->store,
                $this->timeSeriesStore
            )
            ->useLogger($this->log)
            ->setFactories($this->container);

        return $object;
    }

    /**
     * @inheritdoc
     */
    public function setReportScheduleFormData($reportName, Request $request)
    {
        $this->log->debug('Set Report Schedule form data');

        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Set Report Schedule form data
        return $object->setReportScheduleFormData($this->sanitizer->getSanitizer($request->getParams()));
    }

    /**
     * @inheritdoc
     */
    public function generateSavedReportName($reportName, $filterCriteria)
    {
        $this->log->debug('Generate Saved Report name');

        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        $filterCriteria = json_decode($filterCriteria, true);

        return $object->generateSavedReportName($this->sanitizer->getSanitizer($filterCriteria));
    }

    /**
     * @inheritdoc
     */
    public function getSavedReportResults($savedreportId, $reportName): ReportResult|array
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        $savedReport = $this->savedReportFactory->getById($savedreportId);

        $json = $this->readSavedReportJson($savedReport->fileName, $savedReport->storageEngine);

        // Backward-compat: old ZIP files may omit the chart key or store null.
        // Ensure it is always an array so per-report getSavedReportResults() can safely read it.
        if (!array_key_exists('chart', $json) || !is_array($json['chart'])) {
            $json['chart'] = [];
        }

        // Retrieve the saved report result array
        $results = $object->getSavedReportResults($json, $savedReport);

        $this->log->debug('Saved Report results'. json_encode($results, JSON_PRETTY_PRINT));

        // Return data to build chart
        return $results;
    }

    /**
     * @inheritdoc
     */
    public function convertSavedReportResults($savedreportId, $reportName)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        $savedReport = $this->savedReportFactory->getById($savedreportId);

        // Get the old json (saved report)
        $oldjson = $this->readSavedReportJson($savedReport->fileName, $savedReport->storageEngine);

        // Restructure the old json to new json
        $json = $object->restructureSavedReportOldJson($oldjson);

        // A new key keeps the original until the row points at the converted zip.
        $convertedKey = preg_replace('/\.zip$/', '', $savedReport->fileName) . '_v2.zip';
        $stored = $this->storeSavedReportZip($json, $convertedKey);

        // Recording the delete opens the transaction, so the upload record is kept if saving the row fails.
        (new LibraryFileJournal(
            $this->store,
            $this->getSavedReportStorage($savedReport->storageEngine),
            LibraryAsset::SAVED_REPORT
        ))->deleteAfterCommit($savedReport->fileName, $this->log->getLoggerInterface());
        $stored['journal']->clearUploadAfterCommit($stored['journalId']);

        // The converted zip is in the configured store, even when the original waited to be copied.
        if ($savedReport->storageEngine !== null) {
            $this->store->update(
                'UPDATE `saved_report` SET `storageEngine` = NULL WHERE `savedReportId` = :savedReportId',
                ['savedReportId' => $savedReport->savedReportId]
            );
        }

        return [
            'fileName' => $convertedKey,
            'size' => $stored['size'],
            'md5' => $stored['md5'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function storeSavedReportZip(array|\JsonSerializable $results, string $key): array
    {
        $jsonTempPath = tempnam($this->config->getSetting('LIBRARY_LOCATION') . '/temp/', 'reportschedule');
        $zipTempPath = $jsonTempPath . '.zip';
        try {
            file_put_contents($jsonTempPath, json_encode($results));

            $zip = new \ZipArchive();
            $result = $zip->open($zipTempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
            }
            $zip->addFile($jsonTempPath, 'reportschedule.json');
            $zip->close();

            // Size and md5 first: storing the zip removes the local copy.
            $size = filesize($zipTempPath);
            $md5 = md5_file($zipTempPath);

            $journal = new LibraryFileJournal($this->store, $this->getSavedReportStorage(), LibraryAsset::SAVED_REPORT);
            $journalId = $journal->storeLocalFile($zipTempPath, $key, false, $this->log->getLoggerInterface());
        } finally {
            @unlink($jsonTempPath);
            @unlink($zipTempPath);
        }

        return ['journal' => $journal, 'journalId' => $journalId, 'size' => $size, 'md5' => $md5];
    }

    /**
     * Read reportschedule.json from a saved report zip
     * @param string $fileName
     * @param string|null $storageEngine the saved report's storageEngine
     * @return array|null
     * @throws GeneralException
     */
    private function readSavedReportJson(string $fileName, ?string $storageEngine = null): ?array
    {
        $storage = $this->getSavedReportStorage($storageEngine);

        try {
            $zipFile = $storage->downloadToTemp($fileName);
        } catch (NotFoundException) {
            throw new InvalidArgumentException(__('File does not exist'));
        }

        try {
            // Open the Zip file
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new InvalidArgumentException(__('Unable to open ZIP'));
            }

            // Get the reportscheduledetails
            $json = json_decode($zip->getFromName('reportschedule.json'), true);
            $zip->close();
        } finally {
            $storage->releaseTemp($zipFile);
        }

        return $json;
    }

    private function getSavedReportStorage(?string $storageEngine = null): LibraryStorageServiceInterface
    {
        return $this->container->get('libraryStorageRegistry')->get(LibraryAsset::SAVED_REPORT, $storageEngine);
    }

    /**
     * @inheritdoc
     */
    public function runReport($reportName, $filterCriteria, $user)
    {
        $this->log->debug('Run the report to get results');

        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Set userId
        $object->setUser($user);

        $filterCriteria = json_decode($filterCriteria, true);

        // Retrieve the result array
        return $object->getResults($this->sanitizer->getSanitizer($filterCriteria));
    }

    /**
     * @inheritdoc
     */
    public function getReportEmailTemplate($reportName)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Set Report Schedule form data
        return $object->getReportEmailTemplate();
    }

    /**
     * @inheritdoc
     */
    public function getReportChartScriptFromResults(ReportResult|array $results, string $reportName)
    {
        return $this->createReportObject($this->getReportClass($reportName))->getReportChartScript($results);
    }
}
