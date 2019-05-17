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

namespace Xibo\Service;

use Xibo\Exception\NotFoundException;
use Xibo\Factory\SavedReportFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class ReportScheduleService
 * @package Xibo\Service
 */
class ReportService implements ReportServiceInterface
{
    /**
     * @var \Slim\Slim
     */
    public $app;

    /**
     * @var \Xibo\Helper\ApplicationState
     */
    private $state;
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
     * @var DateServiceInterface
     */
    private $date;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizer;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * @inheritdoc
     */
    public function __construct($app, $state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer, $savedReportFactory)
    {
        $this->app = $app;
        $this->state = $state;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->log = $log;
        $this->config = $config;
        $this->date = $date;
        $this->sanitizer = $sanitizer;
        $this->savedReportFactory = $savedReportFactory;
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
            $config->file = str_replace_first(PROJECT_ROOT, '', $file);

            $reports[] = $config;
        }

        return $reports;
    }

    /**
     * @inheritdoc
     */
    public function getReportByName($reportName)
    {
        foreach($this->listReports() as $report) {

            if($report->name == $reportName) {
                return $report;
            }
        }

        //throw error
        throw new NotFoundException(__('No file to return'));
    }

    /**
     * @inheritdoc
     */
    public function getReportClass($reportName)
    {
        foreach($this->listReports() as $report) {

            if($report->name == $reportName) {
                if ($report->class == '') {
                    throw new NotFoundException(__('Report class not found'));
                }
                return $report->class;
            }
        }

        //throw error
        throw new NotFoundException(__('No file to return'));
    }

    /**
     * @inheritdoc
     */
    public function createReportObject($className)
    {
        if (!\class_exists($className))
            throw new NotFoundException(__('Class %s not found', $className));

        $object = new $className(
            $this->state,
            $this->store,
            $this->timeSeriesStore,
            $this->log,
            $this->config,
            $this->date,
            $this->sanitizer);

        $object->setFactories($this->app->container);

        return $object;

    }

    /**
     * @inheritdoc
     */
    public function getReportScheduleFormData($reportName)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Populate form title and hidden fields
        return $object->getReportScheduleFormData();
    }

    /**
     * @inheritdoc
     */
    public function setReportScheduleFormData($reportName)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        // Set Report Schedule form data
        return $object->setReportScheduleFormData();
    }

    /**
     * @inheritdoc
     */
    public function generateSavedReportName($reportName, $filterCriteria)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        $filterCriteria = json_decode($filterCriteria, true);

        return $object->generateSavedReportName($filterCriteria);
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
        $zipFile = $this->config->getSetting('LIBRARY_LOCATION') . $savedReport->storedAs;

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile))
            throw new \InvalidArgumentException(__('File does not exist'));

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile))
            throw new \InvalidArgumentException(__('Unable to open ZIP'));

        // Get the reportscheduledetails
        $json = json_decode($zip->getFromName('reportschedule.json'), true);

        // Retrieve the saved report result array
        $results = $object->getSavedReportResults($json, $savedReport->saveAs);

        // Return data to build chart
        return [
            'template' => $results['template'],
            'chartData' => $results['chartData']
        ];

     }

    /**
     * @inheritdoc
     */
    public function runReport($reportName, $filterCriteria)
    {
        $className = $this->getReportClass($reportName);

        $object = $this->createReportObject($className);

        $filterCriteria = json_decode($filterCriteria, true);

        // Retrieve the result array
        return $object->getResults($filterCriteria);
    }
}
