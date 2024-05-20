<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\AuditLogFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Helper\SendFile;
use Xibo\Support\Exception\InvalidArgumentException;

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
     * @param AuditLogFactory $auditLogFactory
     */
    public function __construct($auditLogFactory)
    {
        $this->auditLogFactory = $auditLogFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
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
        $filterIpAddress = $sanitizedParams->getString('ipAddress');

        if ($filterFromDt != null && $filterFromDt == $filterToDt) {
            $filterToDt->addDay();
        }

        // Get the dates and times
        if ($filterFromDt == null) {
            $filterFromDt = Carbon::now()->sub('1 day');
        }

        if ($filterToDt == null) {
            $filterToDt = Carbon::now();
        }

        $search = [
            'fromTimeStamp' => $filterFromDt->format('U'),
            'toTimeStamp' => $filterToDt->format('U'),
            'userName' => $filterUser,
            'entity' => $filterEntity,
            'entityId' => $filterEntityId,
            'message' => $filterMessage,
            'ipAddress' => $filterIpAddress,
            'sessionHistoryId' => $sanitizedParams->getInt('sessionHistoryId')
        ];

        $rows = $this->auditLogFactory->query(
            $this->gridRenderSort($sanitizedParams),
            $this->gridRenderFilter($search, $sanitizedParams)
        );

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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function exportForm(Request $request, Response $response)
    {
        $this->getState()->template = 'auditlog-form-export';

        return $this->render($request, $response);
    }

    /**
     * Outputs a CSV of audit trail messages
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function export(Request $request, Response $response) : Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // We are expecting some parameters
        $filterFromDt = $sanitizedParams->getDate('filterFromDt');
        $filterToDt = $sanitizedParams->getDate('filterToDt');
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/audittrail_' . Random::generateString();

        if ($filterFromDt == null || $filterToDt == null) {
            throw new InvalidArgumentException(__('Please provide a from/to date.'), 'filterFromDt');
        }

        $fromTimeStamp = $filterFromDt->setTime(0, 0, 0)->format('U');
        $toTimeStamp = $filterToDt->setTime(0, 0, 0)->format('U');

        $rows = $this->auditLogFactory->query('logId', ['fromTimeStamp' => $fromTimeStamp, 'toTimeStamp' => $toTimeStamp]);

        $out = fopen($tempFileName, 'w');
        fputcsv($out, ['ID', 'Date', 'User', 'Entity', 'EntityId', 'Message', 'Object']);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            fputcsv($out, [$row->logId, Carbon::createFromTimestamp($row->logDate)->format(DateFormatHelper::getSystemFormat()), $row->userName, $row->entity, $row->entityId, $row->message, $row->objectAfter]);
        }

        fclose($out);

        $this->setNoOutput(true);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $tempFileName,
            'audittrail.csv'
        )->withHeader('Content-Type', 'text/csv;charset=utf-8'));
    }
}
