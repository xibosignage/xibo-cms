<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
use Slim\Views\Twig;
use Xibo\Factory\AuditLogFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

/**
 * Class AuditLog
 * @package Xibo\Controller
 */
class AuditLog extends Base
{
    /**
     * @var AuditLogFactory
     */
    private $auditLogFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param AuditLogFactory $auditLogFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $auditLogFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->auditLogFactory = $auditLogFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'auditlog-page';

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());

        $filterFromDt = $sanitizedParams->getDate('fromDt');
        $filterToDt = $sanitizedParams->getDate('toDt');
        $filterUser = $sanitizedParams->getString('user');
        $filterEntity = $sanitizedParams->getString('entity');
        $filterEntityId = $sanitizedParams->getString('entityId');
        $filterMessage = $sanitizedParams->getString('message');

        $search = [];

        if ($filterFromDt != null && $filterFromDt == $filterToDt) {
            $filterToDt->addDay();
        }

        // Get the dates and times
        if ($filterFromDt == null) {
            $filterFromDt = $this->getDate()->parse()->sub('1 day');
        }

        if ($filterToDt == null) {
            $filterToDt = $this->getDate()->parse();
        }

        $search['fromTimeStamp'] = $filterFromDt->format('U');
        $search['toTimeStamp'] = $filterToDt->format('U');

        if ($filterUser != '') {
            $search['userName'] = $filterUser;
        }

        if ($filterEntity != '') {
            $search['entity'] = $filterEntity;
        }

        if ($filterEntityId != null) {
            $search['entityId'] = $filterEntityId;
        }

        if ($filterMessage != '') {
            $search['message'] = $filterMessage;
        }

        $rows = $this->auditLogFactory->query($this->gridRenderSort($request), $this->gridRenderFilter($search, $request));

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            $row->objectAfter = json_decode($row->objectAfter);
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->auditLogFactory->countLast();
        $this->getState()->setData($rows);

        return $this->render($request, $response);
    }

    /**
     * Output CSV Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function exportForm(Request $request, Response $response)
    {
        $this->getState()->template = 'auditlog-form-export';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('AuditLog', 'Export')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Outputs a CSV of audit trail messages
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function export(Request $request, Response $response) : Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // We are expecting some parameters
        $filterFromDt = $sanitizedParams->getDate('filterFromDt');
        $filterToDt = $sanitizedParams->getDate('filterToDt');
      //  header( "Content-Type: text/csv;charset=utf-8" );
      //  header( 'Content-Disposition:attachment; filename=audittrail.csv');

        if ($filterFromDt == null || $filterToDt == null) {
            throw new \InvalidArgumentException(__('Please provide a from/to date.'));
        }

        $fromTimeStamp = $filterFromDt->setTime(0, 0, 0)->format('U');
        $toTimeStamp = $filterToDt->setTime(0, 0, 0)->format('U');

        $rows = $this->auditLogFactory->query('logId', ['fromTimeStamp' => $fromTimeStamp, 'toTimeStamp' => $toTimeStamp]);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Date', 'User', 'Entity', 'EntityId', 'Message', 'Object']);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            fputcsv($out, [$row->logId, $this->getDate()->getLocalDate($row->logDate), $row->userName, $row->entity, $row->entityId, $row->message, $row->objectAfter]);
        }

        fclose($out);
        // We want to output a load of stuff to the browser as a text file.
        $response = $response->withHeader('Content-Type', 'text/csv;charset=utf-8')
                             ->withHeader('Content-Disposition', 'attachment; filename="audittrail.csv"')
                             ->withHeader('Content-Transfer-Encoding', 'binary')
                             ->withHeader('Accept-Ranges', 'bytes')
                             ->withHeader('Connection', 'Keep-Alive');

        $this->setNoOutput(true);

        return $this->render($request, $response);
    }
}
