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
use Xibo\Factory\LogFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;

/**
 * Class Logging
 * @package Xibo\Controller
 */
class Logging extends Base
{
    /**
     * @var LogFactory
     */
    private $logFactory;

    /** @var StorageServiceInterface  */
    private $store;

    /** @var  UserFactory */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param LogFactory $logFactory
     * @param UserFactory $userFactory
     */
    public function __construct($store, $logFactory, $userFactory)
    {
        $this->store = $store;
        $this->logFactory = $logFactory;
        $this->userFactory = $userFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $logSortQuery = $this->gridRenderSort(
            $parsedQueryParams,
            $this->isJson($request),
            'logDate'
        );

        $logFilterQuery = $this->getLogFilterQuery($parsedQueryParams);

        $logs = $this->logFactory->query($logSortQuery, $logFilterQuery);

        foreach ($logs as $log) {
            // Normalize the date
            $log->logDate = Carbon::createFromTimeString($log->logDate)->format(DateFormatHelper::getSystemFormat());
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->logFactory->countLast())
            ->withJson($logs);
    }

    /**
     * Truncate the Log
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function truncate(Request $request, Response $response): Response|ResponseInterface
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException(__('Only Administrator Users can truncate the log'));
        }

        $this->store->update('TRUNCATE TABLE log', array());

        // Return
        $this->getState()->hydrate([
            'message' => __('Log Truncated')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get the log filter query
     * @param $parsedQueryParams
     * @return array
     */
    private function getLogFilterQuery($parsedQueryParams): array
    {
        // Date time criteria
        $seconds = $parsedQueryParams->getInt('seconds', ['default' => 120]);
        $intervalType = $parsedQueryParams->getInt('intervalType', ['default' => 1]);
        $fromDt = $parsedQueryParams->getDate('fromDt', ['default' => Carbon::now()]);

        return $this->gridRenderFilter([
            'fromDt' => $fromDt->clone()->subSeconds($seconds * $intervalType)->format('U'),
            'toDt' => $fromDt->format('U'),
            'type' => $parsedQueryParams->getString('level'),
            'page' => $parsedQueryParams->getString('page'),
            'channel' => $parsedQueryParams->getString('channel'),
            'function' => $parsedQueryParams->getString('function'),
            'displayId' => $parsedQueryParams->getInt('displayId'),
            'userId' => $parsedQueryParams->getInt('userId'),
            'excludeLog' => $parsedQueryParams->getCheckbox('excludeLog'),
            'runNo' => $parsedQueryParams->getString('runNo'),
            'message' => $parsedQueryParams->getString('message'),
            'display' => $parsedQueryParams->getString('display'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'displayGroupId' => $parsedQueryParams->getInt('displayGroupId'),
            'keyword' => $parsedQueryParams->getString('keyword')
        ], $parsedQueryParams);
    }
}
