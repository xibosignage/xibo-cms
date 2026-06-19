<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Xibo\Entity\ReportResult;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\SendFile;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SavedReport
 * @package Xibo\Controller
 */
class SavedReport extends Base
{
    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /**
     * @var ReportScheduleFactory
     */
    private $reportScheduleFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param ReportServiceInterface $reportService
     * @param ReportScheduleFactory $reportScheduleFactory
     * @param SavedReportFactory $savedReportFactory
     * @param MediaFactory $mediaFactory
     * @param UserFactory $userFactory
     */
    public function __construct(
        ReportServiceInterface $reportService,
        ReportScheduleFactory $reportScheduleFactory,
        SavedReportFactory $savedReportFactory,
        MediaFactory $mediaFactory,
        UserFactory $userFactory
    ) {
        $this->reportService = $reportService;
        $this->reportScheduleFactory = $reportScheduleFactory;
        $this->savedReportFactory = $savedReportFactory;
        $this->mediaFactory = $mediaFactory;
        $this->userFactory = $userFactory;
    }

    //<editor-fold desc="Saved report">

    /**
     * Saved report Grid
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function savedReportGrid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $savedReportSortQuery = $this->gridRenderSort(
            $sanitizedQueryParams,
            $this->isJson($request),
            'savedReportId'
        );

        $savedReportFilterQuery = $this->getSavedReportFilterQuery($sanitizedQueryParams);

        $savedReports = $this->savedReportFactory->query($savedReportSortQuery, $savedReportFilterQuery);

        foreach ($savedReports as $savedReport) {
            $this->decorateSavedReportProperties($savedReport);
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->savedReportFactory->countLast())
            ->withJson($savedReports);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $savedReport = $this->savedReportFactory->getById($id);

        if (!$this->getUser()->checkViewable($savedReport)) {
            throw new AccessDeniedException(__('You do not have permission to view this saved report.'));
        }

        $this->decorateSavedReportProperties($savedReport);

        return $response
            ->withStatus(200)
            ->withJson($savedReport);
    }

    /**
     * Saved Report Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function savedReportDelete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $savedReport = $this->savedReportFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($savedReport)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));
        }

        $savedReport->load();
        $savedReport->delete();

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $savedReport->saveAs)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Returns a Saved Report's preview
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws ControllerNotImplemented
     */
    public function savedReportOpen(Request $request, Response $response, $id, $name): Response|ResponseInterface
    {
        $savedReport = $this->savedReportFactory->getById($id);

        if (!$this->getUser()->checkViewable($savedReport)) {
            throw new AccessDeniedException(__('You do not have permissions to open the report.'));
        }

        /* @var ReportResult $results */
        $results = $this->reportService->getSavedReportResults($id, $name);

        return $response->withJson($results->jsonSerialize());
    }

    /**
     * Exports saved report as a PDF file
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ControllerNotImplemented
     */
    public function savedReportExport(Request $request, Response $response, $id, $name): Response|ResponseInterface
    {
        $savedReport = $this->savedReportFactory->getById($id);

        if (!$this->getUser()->checkViewable($savedReport)) {
            throw new AccessDeniedException(__('You do not have permissions to export the report.'));
        }

        /* @var ReportResult $results */
        $results = $this->reportService->getSavedReportResults($id, $name);

        $report = $this->reportService->getReportByName($name);

        if ($report->output_type == 'both' || $report->output_type == 'chart') {
            $quickChartUrl = $this->getConfig()->getSetting('QUICK_CHART_URL');

            if (!empty($quickChartUrl)) {
                $quickChartUrl .= '/chart?width=1000&height=300&c=';
                $script = $this->reportService->getReportChartScript($id, $name);

                // Replace " with ' for the quick chart URL
                $src = $quickChartUrl . str_replace('"', '\'', $script);

                // If multiple charts needs to be displayed
                $multipleCharts = [];
                $chartScriptArray = json_decode($script, true);

                foreach ($chartScriptArray as $key => $chartData) {
                    $multipleCharts[$key] = $quickChartUrl . str_replace('"', '\'', json_encode($chartData));
                }
            } else {
                $placeholder = __('Chart could not be drawn because the CMS has not been configured with a Quick Chart URL.');
            }
        }

        // only for tablebased report
        if ($report->output_type == 'both' || $report->output_type == 'table') {
            $tableData = $results->table;
        }

        // Get report email template to export
        $emailTemplate = $this->reportService->getReportEmailTemplate($name);

        if (!empty($emailTemplate)) {
            // Save PDF attachment
            $showLogo = $this->getConfig()->getSetting('REPORTS_EXPORT_SHOW_LOGO', 1) == 1;

            $body = $this->getView()->fetch(
                $emailTemplate,
                [
                    'header' => $report->description,
                    'logo' => ($showLogo) ? rtrim($this->getConfig()->getSetting('LIBRARY_LOCATION'), '/') . '/brand/xibologo.png' : null,
                    'title' => $savedReport->saveAs,
                    'metadata' => $results->metadata,
                    'tableData' => $tableData ?? null,
                    'src' => $src ?? null,
                    'multipleCharts' => $multipleCharts ?? null,
                    'placeholder' => $placeholder ?? null
                ]
            );

            $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/saved_report_' . $id . '.pdf';

            try {
                $mpdf = new \Mpdf\Mpdf([
                    'tempDir' => $this->getConfig()->getSetting('LIBRARY_LOCATION') . '/temp',
                    'orientation' => 'L',
                    'mode' => 'c',
                    'margin_left' => 20,
                    'margin_right' => 20,
                    'margin_top' => 20,
                    'margin_bottom' => 20,
                    'margin_header' => 5,
                    'margin_footer' => 15
                ]);
                $mpdf->setFooter('Page {PAGENO}');
                $mpdf->SetDisplayMode('fullpage');
                $stylesheet =  file_get_contents(PROJECT_ROOT . '/web/css/email-report.css');
                $mpdf->WriteHTML($stylesheet, 1);
                $mpdf->WriteHTML($body);
                $mpdf->Output($fileName, \Mpdf\Output\Destination::FILE);
            } catch (\Exception $error) {
                $this->getLog()->error($error->getMessage());
            }
        }

        // Return the file with PHP
        $this->setNoOutput(true);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $fileName
        ));
    }

    /**
     * Converts a Saved Report from Schema Version 1 to 2
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws ControllerNotImplemented
     */
    public function savedReportConvert(Request $request, Response $response, $id, $name): Response|ResponseInterface
    {
        $savedReport = $this->savedReportFactory->getById($id);

        if ($savedReport->schemaVersion == 2) {
            throw new GeneralException(__('This report has already been converted to the latest version.'));
        }

        // Convert Result to schemaVersion 2
        $this->reportService->convertSavedReportResults($id, $name);

        $savedReport->schemaVersion = 2;
        $savedReport->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Saved Report Converted to Schema Version 2')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Decorate saved report properties with user permissions
     * @throws InvalidArgumentException|GeneralException
     */
    private function decorateSavedReportProperties($savedReport): void
    {
        $savedReport->setUnmatchedProperty(
            'userPermissions',
            $this->getUser()->getPermission($savedReport)
        );

        $savedReport->setUnmatchedProperty(
            'emailTemplate',
            $this->reportService->getReportEmailTemplate($savedReport->reportName)
        );
    }

    /**
     * Get saved report filter query
     */
    private function getSavedReportFilterQuery($sanitizedQueryParams): array
    {
        return $this->gridRenderFilter([
            'saveAs' => $sanitizedQueryParams->getString('saveAs'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'userId' => $sanitizedQueryParams->getInt('userId'),
            'reportName' => $sanitizedQueryParams->getString('reportName'),
            'onlyMyReport' => $sanitizedQueryParams->getCheckbox('onlyMyReport'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
            'keyword' => $sanitizedQueryParams->getString('keyword'),
        ], $sanitizedQueryParams);
    }

    //</editor-fold>
}
