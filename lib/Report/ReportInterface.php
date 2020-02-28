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


use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Psr\Container\ContainerInterface;

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
     * Return the twig file name of the report form
     * Load the report form
     * @return string
     */
    public function getReportForm();

    /**
     * Return the twig file name of the report email template
     * @return string
     */
    public function getReportEmailTemplate();

    /**
     * Get chart script
     * @return string
     */
    public function getReportChartScript($results);

    /**
     * Populate form title and hidden fields
     * @param Request $request
     * @return array
     */
    public function getReportScheduleFormData(Request $request);

    /**
     * Set Report Schedule form data
     * @param Request $request
     * @return array
     */
    public function setReportScheduleFormData(Request $request);

    /**
     * Generate saved report name
     * @param $filterCriteria
     * @return string
     */
    public function generateSavedReportName($filterCriteria);

    /**
     * Return data to build chart of saved report
     * @param array $json
     * @param object savedReport
     * @return array
     */
    public function getSavedReportResults($json, $savedReport);

    /**
     * Return results
     * @param $filterCriteria
     * @return array
     */
    public function getResults($filterCriteria);
}