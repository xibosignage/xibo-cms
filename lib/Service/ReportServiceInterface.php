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

use Slim\Slim;
use Xibo\Factory\SavedReportFactory;
use Xibo\Report\ReportInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Interface ReportServiceInterface
 * @package Xibo\Service
 */
interface ReportServiceInterface
{
    /**
     * ReportServiceInterface constructor.
     * @param Slim $app
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @param SavedReportFactory $savedReportFactory
 */
    public function __construct($app, $state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer, $savedReportFactory);

    // List all reports that are available
    public function listReports();

    /**
     * Get report by report name
     * @param string $reportName
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getReportByName($reportName);

    /**
     * Get report class by report name
     * @param string $reportName
     */
    public function getReportClass($reportName);

    /**
     * Create the report object by report classname
     * @param string $className
     * @return ReportInterface
     */
    public function createReportObject($className);

    /**
     * Populate form title and hidden fields
     * @param string $reportName
     * @return array
     */
    public function getReportScheduleFormData($reportName);

    /**
     * Set Report Schedule form data
     * @param string $reportName
     * @return array
     */
    public function setReportScheduleFormData($reportName);

    /**
     * Generate saved report name
     * @param string $reportName
     * @param string $filterCriteria
     * @return string
     */
    public function generateSavedReportName($reportName, $filterCriteria);

    /**
     * Get saved report results
     * @param int $savedreportId
     * @param string $reportName
     * @return array
     */
    public function getSavedReportResults($savedreportId, $reportName);

    /**
     * Run the report
     * @param string $reportName
     * @param string $filterCriteria
     * @param int $userId
     * @return array
     */
    public function runReport($reportName, $filterCriteria, $userId);

    /**
     * Get report email template twig file name
     * @param string $reportName
     * @return string
     */
    public function getReportEmailTemplate($reportName);

    /**
     * Get chart script
     * @param int $savedreportId
     * @param string $reportName
     * @return array
     */
    public function getReportChartScript($savedreportId, $reportName);
}