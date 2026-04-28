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
use OpenApi\Attributes as OA;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\SessionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class Sessions
 * @package Xibo\Controller
 */
#[OA\Schema(
    schema: 'Session',
    properties: [
        new OA\Property(property: 'userId', type: 'integer'),
        new OA\Property(property: 'userName', type: 'string'),
        new OA\Property(property: 'isExpired', type: 'integer'),
        new OA\Property(property: 'lastAccessed', type: 'string', format: 'date-time'),
        new OA\Property(property: 'remoteAddress', type: 'string'),
        new OA\Property(property: 'userAgent', type: 'string'),
        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'userPermissions', type: 'object'),
    ],
    type: 'object',
)]
class Sessions extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var SessionFactory
     */
    private $sessionFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param SessionFactory $sessionFactory
     */
    public function __construct($store, $sessionFactory)
    {
        $this->store = $store;
        $this->sessionFactory = $sessionFactory;
    }

    #[OA\Get(
        path: '/sessions',
        operationId: 'sessionSearch',
        description: 'Search Sessions this user has access to',
        summary: 'Session Search',
        tags: ['session']
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filter by type',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: ['all', 'active', 'guest', 'expired']
        )
    )]
    #[OA\Parameter(
        name: 'lastAccessedDateFrom',
        description: 'Start date for filtering sessions by last accessed date',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'date', format: 'date')
    )]
    #[OA\Parameter(
        name: 'lastAccessedDateTo',
        description: 'End date for filtering sessions by last accessed date',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'lastAccessed',
                'isExpired',
                'userName',
                'remoteAddress',
                'userAgent',
                'expiresAt',
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Session')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response): Response|\Psr\Http\Message\ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $sessionsSortQuery = $this->gridRenderSort(
            $sanitizedQueryParams,
            $this->isJson($request),
            'lastAccessed'
        );
        $sessionsFilterQuery = $this->getSessionsFilter($sanitizedQueryParams);

        $sessions = $this->sessionFactory->query($sessionsSortQuery, $sessionsFilterQuery);

        foreach ($sessions as $session) {
            // Normalise the date
            $session->lastAccessed =
                Carbon::createFromTimeString($session->lastAccessed)?->format(DateFormatHelper::getSystemFormat());
            $session->expiresAt =
                Carbon::createFromTimestamp($session->expiresAt)?->format(DateFormatHelper::getSystemFormat());
            $session->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($session));
        }

        $recordsTotal = $this->sessionFactory->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($sessions);
    }

    #[OA\Delete(
        path: '/sessions/logout/{id}',
        operationId: 'sessionLogout',
        description: 'Logs out all sessions for the given user',
        summary: 'Session Logout',
        tags: ['session']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The User ID to log out',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
            ],
            type: 'object'
        )
    )]
    /**
     * Logout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function logout(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        // We log out all of this user's sessions.
        $this->sessionFactory->expireByUserId($id);

        return $response->withJson([
            'success' => true,
            'message' => __('User Logged Out.'),
        ], 200);
    }

    /**
     * Get the sessions filters
     * @param $sanitizedQueryParams
     * @return array
     */
    private function getSessionsFilter($sanitizedQueryParams): array
    {
        return $this->gridRenderFilter([
            'type' => $sanitizedQueryParams->getString('type'),
            'fromDt' => $sanitizedQueryParams->getString('fromDt'),
            'lastAccessedDateFrom' => $sanitizedQueryParams->getString('lastAccessedDateFrom'),
            'lastAccessedDateTo' => $sanitizedQueryParams->getString('lastAccessedDateTo'),
        ], $sanitizedQueryParams);
    }
}
