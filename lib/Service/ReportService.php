<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Service;

use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\ReportResult;
use Xibo\Event\ConnectorReportEvent;
use Xibo\Factory\SavedReportFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Report\ReportInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ReportScheduleService
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

        $files = array_merge(glob(PROJECT_ROOT . '/reports/*.report'), glob(PROJECT_ROOT . '/custom/*.report'));

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

        // Get reports that are allowed by connectors
        $event = new ConnectorReportEvent();
        $this->getDispatcher()->dispatch($event, ConnectorReportEvent::$NAME);
        $connectorReports = $event->getReports();

        // Merge built in reports and connector reports
        if (count($connectorReports) > 0) {
            $reports = array_merge($reports, $connectorReports);
        }

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
    public function getReportScheduleFormData($reportName, Request $request)
    {
        $this->log->debug('Populate form title and hidden fields');

        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Populate form title and hidden fields
        return $object->getReportScheduleFormData($this->sanitizer->getSanitizer($request->getParams()));
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
    public function getSavedReportResults($savedreportId, $reportName)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        $savedReport = $this->savedReportFactory->getById($savedreportId);

        // Open a zipfile and read the json
        $zipFile = $this->config->getSetting('LIBRARY_LOCATION') .'savedreport/'. $savedReport->fileName;

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile)) {
            throw new InvalidArgumentException(__('File does not exist'));
        }

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile)) {
            throw new InvalidArgumentException(__('Unable to open ZIP'));
        }

        // Get the reportscheduledetails
        $json = json_decode($zip->getFromName('reportschedule.json'), true);

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

        // Open a zipfile and read the json
        $zipFile = $this->config->getSetting('LIBRARY_LOCATION') . $savedReport->storedAs;

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile)) {
            throw new InvalidArgumentException(__('File does not exist'));
        }

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile)) {
            throw new InvalidArgumentException(__('Unable to open ZIP'));
        }

        // Get the old json (saved report)
        $oldjson = json_decode($zip->getFromName('reportschedule.json'), true);

        // Restructure the old json to new json
        $json = $object->restructureSavedReportOldJson($oldjson);

        // Format the JSON as schemaVersion 2
        $fileName = tempnam($this->config->getSetting('LIBRARY_LOCATION') . '/temp/', 'reportschedule');
        $out = fopen($fileName, 'w');
        fwrite($out, json_encode($json));
        fclose($out);

        $zip = new \ZipArchive();
        $result = $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: %s', $result));
        }

        $zip->addFile($fileName, 'reportschedule.json');
        $zip->close();

        // Remove the JSON file
        unlink($fileName);
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
    public function getSavedReportTemplate($reportName)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Set Report Schedule form data
        return $object->getSavedReportTemplate();
    }

    /**
     * @inheritdoc
     */
    public function getReportChartScript($savedreportId, $reportName)
    {
        /* @var ReportResult $results */
        $results = $this->getSavedReportResults($savedreportId, $reportName);

        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Set Report Schedule form data
        return $object->getReportChartScript($results);
    }
}
