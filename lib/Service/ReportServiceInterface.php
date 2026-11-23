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

use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportResult;
use Xibo\Factory\SavedReportFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Report\ReportInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\GeneralException;

/*
 * Interface ReportServiceInterface
 * @package Xibo\Service
 */
interface ReportServiceInterface
{
    /**
     * ReportServiceInterface constructor.
     * @param \Psr\Container\ContainerInterface $app
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param SanitizerService $sanitizer
     * @param SavedReportFactory $savedReportFactory
     */
    public function __construct(
        $app,
        $store,
        $timeSeriesStore,
        $log,
        $config,
        $sanitizer,
        $savedReportFactory
    );

    /**
     * List all reports that are available
     * @return array
     */
    public function listReports();

    /**
     * Get report by report name
     * @param string $reportName
     * @throws GeneralException
     */
    public function getReportByName($reportName);

    /**
     * Get report class by report name
     * @param string $reportName
     * @throws GeneralException
     */
    public function getReportClass($reportName);

    /**
     * Create the report object by report classname
     * @param string $className
     * @throws GeneralException
     * @return ReportInterface
     */
    public function createReportObject($className);

    /**
     * Set Report Schedule form data
     * @param string $reportName
     * @param Request $request
     * @throws GeneralException
     * @return array
     */
    public function setReportScheduleFormData($reportName, Request $request);

    /**
     * Generate saved report name
     * @param string $reportName
     * @param string $filterCriteria
     * @throws GeneralException
     * @return string
     */
    public function generateSavedReportName($reportName, $filterCriteria);

    /**
     * Get saved report results
     * @param int $savedreportId
     * @param string $reportName
     * @throws GeneralException
     * @return array
     */
    public function getSavedReportResults($savedreportId, $reportName);

    /**
     * Convert saved report results from old schema 1 to schema version 2
     * @param int $savedreportId
     * @param string $reportName
     * @throws GeneralException
     * @return array the new zip's fileName, size and md5, for the caller to save on the saved report
     */
    public function convertSavedReportResults($savedreportId, $reportName);

    /**
     * Zips $results as reportschedule.json and moves it into saved report storage under $key, recording the upload in
     * the returned journal for the caller to clear once its saved report row commits.
     * @param array|\JsonSerializable $results
     * @param string $key
     * @return array{journal: LibraryFileJournal, journalId: int, size: int, md5: string}
     * @throws GeneralException
     */
    public function storeSavedReportZip(array|\JsonSerializable $results, string $key): array;

    /**
     * Run the report
     * @param string $reportName
     * @param string $filterCriteria
     * @param \Xibo\Entity\User $user
     * @throws GeneralException
     * @return array
     */
    public function runReport($reportName, $filterCriteria, $user);

    /**
     * Get report email template twig file name
     * @param string $reportName
     * @throws GeneralException
     * @return string
     */
    public function getReportEmailTemplate($reportName);

    /**
     * Get the chart script for saved report results already read
     * @return string|null
     * @throws GeneralException
     */
    public function getReportChartScriptFromResults(ReportResult|array $results, string $reportName);
}
