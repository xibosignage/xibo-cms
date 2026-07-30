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

use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\AuditLogFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Helper\SendFile;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class AuditLog
 *
 * @package Xibo\Controller
 */
class AuditLog extends Base
{
    /**
     * AuditLog constructor.
     *
     * @param AuditLogFactory $auditLogFactory Audit log factory
     */
    public function __construct(private readonly AuditLogFactory $auditLogFactory)
    {
    }

    /**
     * Get the list of audit logs
     *
     * @param  Request  $request
     * @param  Response $response
     * @return ResponseInterface|Response
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());

        $auditLogSortQuery = $this->gridRenderSort(
            $sanitizedParams,
            $this->isJson($request),
            'logId'
        );

        $auditLogFilterQuery = $this->getAuditLogFilterQuery($sanitizedParams);

        $auditLogs = $this->auditLogFactory->query($auditLogSortQuery, $auditLogFilterQuery);

        foreach ($auditLogs as $auditLog) {
            $auditLog->objectAfter = json_decode($auditLog->objectAfter);
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->auditLogFactory->countLast())
            ->withJson($auditLogs);
    }

    /**
     * Audit log search by ID
     *
     * @param  Request  $request
     * @param  Response $response
     * @param  int      $id
     * @return Response|ResponseInterface
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $auditLog = $this->auditLogFactory->getById($id);

        $auditLog->objectAfter = json_decode($auditLog->objectAfter);

        return $response
            ->withStatus(200)
            ->withJson($auditLog);
    }

    /**
     * Outputs a CSV of audit trail messages
     *
     * @param  Request  $request
     * @param  Response $response
     * @return Response
     * @throws InvalidArgumentException
     */
    public function export(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $filterFromDt = $sanitizedParams->getDate('filterFromDt');
        $filterToDt = $sanitizedParams->getDate('filterToDt');

        if ($filterFromDt === null || $filterToDt === null) {
            throw new InvalidArgumentException(__('Please provide a from/to date.'), 'filterFromDt');
        }

        $fromTimeStamp = $filterFromDt->setTime(0, 0, 0)->format('U');
        $toTimeStamp = $filterToDt->setTime(0, 0, 0)->format('U');

        $rows = $this->auditLogFactory->query(
            ['logId'],
            ['fromTimeStamp' => $fromTimeStamp, 'toTimeStamp' => $toTimeStamp]
        );

        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/audittrail_' . Random::generateString();
        $out = fopen($tempFileName, 'w');
        fputcsv($out, ['ID', 'Date', 'User', 'Entity', 'EntityId', 'Message', 'Object'], ',', '"', '\\');

        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            fputcsv(
                $out,
                [
                    $row->logId,
                    Carbon::createFromTimestamp($row->logDate)->format(DateFormatHelper::getSystemFormat()),
                    $row->userName,
                    $row->entity,
                    $row->entityId,
                    $row->message,
                    $row->objectAfter,
                ],
                ',', '"', '\\'
            );
        }

        fclose($out);

        return SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $tempFileName,
            'audittrail.csv'
        )->withHeader('Content-Type', 'text/csv;charset=utf-8');
    }

    /**
     * Build the audit log filter query array from sanitized request params.
     *
     * @param SanitizerInterface $sanitizedParams The sanitized request parameters
     *
     * @return array
     */
    private function getAuditLogFilterQuery(SanitizerInterface $sanitizedParams): array
    {
        $filterFromDt = $sanitizedParams->getDate('fromDt');
        $filterToDt = $sanitizedParams->getDate('toDt');

        if ($filterFromDt != null && $filterFromDt == $filterToDt) {
            $filterToDt->addDay();
        }

        if ($filterFromDt == null) {
            $filterFromDt = Carbon::now()->sub('1 day');
        }

        if ($filterToDt == null) {
            $filterToDt = Carbon::now();
        }

        return $this->gridRenderFilter(
            [
                'fromTimeStamp' => $filterFromDt->format('U'),
                'toTimeStamp' => $filterToDt->format('U'),
                'userName' => $sanitizedParams->getString('user'),
                'entity' => $sanitizedParams->getString('entity'),
                'entityId' => $sanitizedParams->getString('entityId'),
                'message' => $sanitizedParams->getString('message'),
                'ipAddress' => $sanitizedParams->getString('ipAddress'),
                'sessionHistoryId' => $sanitizedParams->getInt('sessionHistoryId'),
            ], $sanitizedParams
        );
    }
}
