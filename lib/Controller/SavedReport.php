<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportResult;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\SendFile;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
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
    public function __construct($reportService, $reportScheduleFactory, $savedReportFactory, $mediaFactory, $userFactory)
    {
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
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function savedReportGrid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $savedReports = $this->savedReportFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter([
            'saveAs' => $sanitizedQueryParams->getString('saveAs'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'userId' => $sanitizedQueryParams->getInt('userId'),
            'reportName' => $sanitizedQueryParams->getString('reportName'),
            'onlyMyReport' => $sanitizedQueryParams->getCheckbox('onlyMyReport'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
        ], $sanitizedQueryParams));

        foreach ($savedReports as $savedReport) {
            if ($this->isApi($request)) {
                continue;
            }

            $savedReport->includeProperty('buttons');

            // If a report class does not comply (i.e., no category or route) we get an error when trying to get the email template
            // Dont show any button if the report is not compatible
            // This will also check whether the report feature is enabled or not.
            $compatible = true;
            try {
                // Get report email template
                $emailTemplate = $this->reportService->getReportEmailTemplate($savedReport->reportName);
            } catch (NotFoundException $exception) {
                $compatible = false;
            }

            if ($compatible) {
                // Show only convert button for schema version 1
                if ($savedReport->schemaVersion == 1) {
                    $savedReport->buttons[] = [
                        'id' => 'button_convert_report',
                        'url' => $this->urlFor($request, 'savedreport.convert.form', ['id' => $savedReport->savedReportId]),
                        'text' => __('Convert')
                    ];
                } else {
                    $savedReport->buttons[] = [
                        'id' => 'button_show_report.now',
                        'class' => 'XiboRedirectButton',
                        'url' => $this->urlFor($request, 'savedreport.open', ['id' => $savedReport->savedReportId, 'name' => $savedReport->reportName]),
                        'text' => __('Open')
                    ];
                    $savedReport->buttons[] = ['divider' => true];

                    $savedReport->buttons[] = [
                        'id' => 'button_goto_report',
                        'class' => 'XiboRedirectButton',
                        'url' => $this->urlFor($request, 'report.form', ['name' => $savedReport->reportName]),
                        'text' => __('Back to Reports')
                    ];

                    $savedReport->buttons[] = [
                        'id' => 'button_goto_schedule',
                        'class' => 'XiboRedirectButton',
                        'url' => $this->urlFor($request, 'reportschedule.view') . '?reportScheduleId=' . $savedReport->reportScheduleId. '&reportName='.$savedReport->reportName,
                        'text' => __('Go to schedule')
                    ];

                    $savedReport->buttons[] = ['divider' => true];

                    if (!empty($emailTemplate)) {
                        // Export Button
                        $savedReport->buttons[] = [
                            'id' => 'button_export_report',
                            'linkType' => '_self', 'external' => true,
                            'url' => $this->urlFor($request, 'savedreport.export', ['id' => $savedReport->savedReportId, 'name' => $savedReport->reportName]),
                            'text' => __('Export as PDF')
                        ];
                    }

                    // Delete
                    if ($this->getUser()->checkDeleteable($savedReport)) {
                        // Show the delete button
                        $savedReport->buttons[] = array(
                            'id' => 'savedreport_button_delete',
                            'url' => $this->urlFor($request, 'savedreport.delete.form', ['id' => $savedReport->savedReportId]),
                            'text' => __('Delete'),
                            'multi-select' => true,
                            'dataAttributes' => array(
                                array('name' => 'commit-url', 'value' => $this->urlFor($request, 'savedreport.delete', ['id' => $savedReport->savedReportId])),
                                array('name' => 'commit-method', 'value' => 'delete'),
                                array('name' => 'id', 'value' => 'savedreport_button_delete'),
                                array('name' => 'text', 'value' => __('Delete')),
                                array('name' => 'sort-group', 'value' => 1),
                                array('name' => 'rowtitle', 'value' => $savedReport->saveAs),
                            )
                        );
                    }
                }
            }
        }
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->savedReportFactory->countLast();
        $this->getState()->setData($savedReports);

        return $this->render($request, $response);
    }

    /**
     * Displays the Saved Report Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displaySavedReportPage(Request $request, Response $response)
    {
        $reportsList = $this->reportService->listReports();
        $availableReports = [];
        foreach ($reportsList as $reports) {
            foreach ($reports as $report) {
                $availableReports[] = $report;
            }
        }

        // Call to render the template
        $this->getState()->template = 'saved-report-page';
        $this->getState()->setData([
            'availableReports' => $availableReports
        ]);

        return $this->render($request, $response);
    }

    /**
     * Report Schedule Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteSavedReportForm(Request $request, Response $response, $id)
    {
        $savedReport = $this->savedReportFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($savedReport)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));
        }

        $data = [
            'savedReport' => $savedReport
        ];

        $this->getState()->template = 'savedreport-form-delete';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Saved Report Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function savedReportDelete(Request $request, Response $response, $id)
    {

        $savedReport = $this->savedReportFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($savedReport)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this report schedule'));
        }

        $savedReport->load();

        // Delete
        $savedReport->delete();

        // Return
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
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function savedReportOpen(Request $request, Response $response, $id, $name)
    {
        // Retrieve the saved report result in array
        /* @var ReportResult $results */
        $results = $this->reportService->getSavedReportResults($id, $name);

        // Set Template
        $this->getState()->template = $this->reportService->getSavedReportTemplate($name);

        $this->getState()->setData($results->jsonSerialize());

        return $this->render($request, $response);
    }

    /**
     * Exports saved report as a PDF file
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function savedReportExport(Request $request, Response $response, $id, $name)
    {
        $savedReport = $this->savedReportFactory->getById($id);

        // Retrieve the saved report result in array
        /* @var ReportResult $results */
        $results = $this->reportService->getSavedReportResults($id, $name);

        // Get the report config
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

        if ($report->output_type == 'both' || $report->output_type == 'table') { // only for tablebased report
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
                    'logo' => ($showLogo) ? $this->getConfig()->uri('img/xibologo.png', true) : null,
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
                $mpdf->setFooter('Page {PAGENO}') ;
                $mpdf->SetDisplayMode('fullpage');
                $stylesheet =  file_get_contents($this->getConfig()->uri('css/email-report.css', true));
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
     * Saved Report Convert Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function convertSavedReportForm(Request $request, Response $response, $id)
    {
        $savedReport = $this->savedReportFactory->getById($id);

        $data = [
            'savedReport' => $savedReport
        ];

        $this->getState()->template = 'savedreport-form-convert';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Converts a Saved Report from Schema Version 1 to 2
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $name
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function savedReportConvert(Request $request, Response $response, $id, $name)
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
            'message' => sprintf(__('Saved Report Converted to Schema Version 2'))
        ]);

        return $this->render($request, $response);
    }

    //</editor-fold>
}
