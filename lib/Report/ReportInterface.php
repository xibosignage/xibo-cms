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

namespace Xibo\Report;

use Psr\Container\ContainerInterface;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Interface ReportInterface
 * @package Xibo\Report
 */
interface ReportInterface
{
    /**
     * Set factories
     * @param ContainerInterface $container
     * @return $this
     */
    public function setFactories(ContainerInterface $container);

    /**
     * Set user Id
     * @param \Xibo\Entity\User $user
     * @return $this
     */
    public function setUser($user);

    /**
     * Get the user
     * @return \Xibo\Entity\User
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getUser();

    /**
     * Get chart script
     * @param ReportResult $results
     * @return string
     */
    public function getReportChartScript($results);

    /**
     * Return the twig file name of the saved report email and export template
     * @return string
     */
    public function getReportEmailTemplate();

    /**
     * Return the twig file name of the saved report preview template
     * @return string
     */
    public function getSavedReportTemplate();

    /**
     * Return the twig file name of the report form
     * Load the report form
     * @return ReportForm
     */
    public function getReportForm();

    /**
     * Populate form title and hidden fields
     * @param SanitizerInterface $sanitizedParams
     * @return array
     */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams);

    /**
     * Set Report Schedule form data
     * @param SanitizerInterface $sanitizedParams
     * @return array
     */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams);

    /**
     * Generate saved report name
     * @param SanitizerInterface $sanitizedParams
     * @return string
     */
    public function generateSavedReportName(SanitizerInterface $sanitizedParams);

    /**
     * Resrtucture old saved report's json file to support schema version 2
     * @param $json
     * @return array
     */
    public function restructureSavedReportOldJson($json);

    /**
     * Return data from saved json file to build chart/table for saved report
     * @param array $json
     * @param object $savedReport
     * @return ReportResult
     */
    public function getSavedReportResults($json, $savedReport);

    /**
     * Get results when on demand report runs and
     * This result will get saved to a json if schedule report runs
     * @param SanitizerInterface $sanitizedParams
     * @return ReportResult
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getResults(SanitizerInterface $sanitizedParams);
}
