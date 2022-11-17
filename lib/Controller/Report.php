<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportResult;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Class Report
 * @package Xibo\Controller
 */
class Report extends Base
{
    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /**
     * Set common dependencies.
     * @param ReportServiceInterface $reportService
     */
    public function __construct($reportService)
    {
        $this->reportService = $reportService;
    }

    /// //<editor-fold desc="Ad hoc reports">

    /**
     * Displays an Ad Hoc Report form
     * @param Request $request
     * @param Response $response
     * @param $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getReportForm(Request $request, Response $response, $name)
    {
        $this->getLog()->debug('Get report name: '. $name);

        // Get the report Class from the Json File
        $className = $this->reportService->getReportClass($name);

        // Create the report object
        $object = $this->reportService->createReportObject($className);
        
        // We assert the user so that we can use getUser in the report class
        $object->setUser($this->getUser());

        // Get the twig file template and required data of the report form
        $form =  $object->getReportForm();

        // Show the twig
        $this->getState()->template = $form->template;
        $this->getState()->setData([
            'reportName' => $form->reportName,
            'reportCategory' => $form->reportCategory,
            'reportAddBtnTitle' => $form->reportAddBtnTitle,
            'availableReports' => $this->reportService->listReports(),
            'defaults' => $form->defaults
        ]);

        return $this->render($request, $response);
    }

    /**
     * Displays Ad Hoc/ On demand Report data in charts
     * @param Request $request
     * @param Response $response
     * @param $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getReportData(Request $request, Response $response, $name)
    {
        $this->getLog()->debug('Get report name: '. $name);

        // Get the report Class from the Json File
        $className = $this->reportService->getReportClass($name);

        // Create the report object
        $object = $this->reportService->createReportObject($className)->setUser($this->getUser());

        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Return data to build chart/table
        $result =  $object->getResults($sanitizedParams);

        //
        // Output Results
        // --------------
        return $response->withJson($result);
    }

    //</editor-fold>
}
