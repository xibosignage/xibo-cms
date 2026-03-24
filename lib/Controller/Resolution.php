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

use OpenApi\Attributes as OA;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\ResolutionFactory;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class Resolution
 * @package Xibo\Controller
 */
class Resolution extends Base
{
    /**
     * @var ResolutionFactory
     */
    private $resolutionFactory;

    /**
     * Set common dependencies.
     * @param ResolutionFactory $resolutionFactory
     */
    public function __construct($resolutionFactory)
    {
        $this->resolutionFactory = $resolutionFactory;
    }

    /**
     * Display the Resolution Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'resolution-page';

        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/resolution',
        operationId: 'resolutionSearch',
        description: 'Search Resolutions this user has access to',
        summary: 'Resolution Search',
        tags: ['resolution']
    )]
    #[OA\Parameter(
        name: 'resolutionId',
        description: 'Filter by Resolution Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by Resolution name, ID, height, or width',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'resolution',
        description: 'Filter by Resolution Name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'partialResolution',
        description: 'Filter by Partial Resolution Name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'enabled',
        description: 'Filter by Enabled',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'width',
        description: 'Filter by Resolution width',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'height',
        description: 'Filter by Resolution height',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'resolutionId',
                'resolution',
                'width',
                'height',
                'enabled',
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
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Resolution')
        )
    )]
    /**
     * Resolution Grid
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $resolutionSortQuery = $this->gridRenderSort(
            $sanitizedQueryParams,
            $this->isJson($request),
            'resolution'
        );
        $resolutionFilterQuery = $this->getResolutionFilter($sanitizedQueryParams);

        $resolutions = $this->resolutionFactory->query($resolutionSortQuery, $resolutionFilterQuery);

        // Add user permissions
        foreach ($resolutions as $resolution) {
            $resolution->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($resolution));
        }

        $recordsTotal = $this->resolutionFactory->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($resolutions);
    }

    #[OA\Post(
        path: '/resolution',
        operationId: 'resolutionAdd',
        description: 'Add new Resolution',
        summary: 'Add Resolution',
        tags: ['resolution']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['resolution', 'width', 'height'],
                properties: [
                    new OA\Property(property: 'resolution', description: 'A name for the Resolution', type: 'string'),
                    new OA\Property(
                        property: 'width',
                        description: 'The Display Width of the Resolution',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'height',
                        description: 'The Display Height of the Resolution',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/Resolution')
    )]
    /**
     * Add Resolution
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $resolution = $this->resolutionFactory->create(
            $sanitizedParams->getString('resolution'),
            $sanitizedParams->getInt('width'),
            $sanitizedParams->getInt('height')
        );

        $resolution->userId = $this->getUser()->userId;

        $resolution->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $resolution->resolution),
            'id' => $resolution->resolutionId,
            'data' => $resolution
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/resolution/{resolutionId}',
        operationId: 'resolutionEdit',
        description: 'Edit new Resolution',
        summary: 'Edit Resolution',
        tags: ['resolution']
    )]
    #[OA\Parameter(
        name: 'resolutionId',
        description: 'The Resolution ID to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['resolution', 'width', 'height'],
                properties: [
                    new OA\Property(property: 'resolution', description: 'A name for the Resolution', type: 'string'),
                    new OA\Property(
                        property: 'width',
                        description: 'The Display Width of the Resolution',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'height',
                        description: 'The Display Height of the Resolution',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Resolution')
    )]
    /**
     * Edit Resolution
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function edit(Request $request, Response $response, $id)
    {
        $resolution = $this->resolutionFactory->getById($id);

        if (!$this->getUser()->checkEditable($resolution)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $resolution->resolution = $sanitizedParams->getString('resolution');
        $resolution->width = $sanitizedParams->getInt('width');
        $resolution->height = $sanitizedParams->getInt('height');
        $resolution->enabled = $sanitizedParams->getCheckbox('enabled');

        $resolution->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $resolution->resolution),
            'id' => $resolution->resolutionId,
            'data' => $resolution
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/resolution/{resolutionId}',
        operationId: 'resolutionDelete',
        description: 'Delete Resolution',
        summary: 'Delete Resolution',
        tags: ['resolution']
    )]
    #[OA\Parameter(
        name: 'resolutionId',
        description: 'The Resolution ID to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete Resolution
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function delete(Request $request, Response $response, $id)
    {
        $resolution = $this->resolutionFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($resolution)) {
            throw new AccessDeniedException();
        }

        $resolution->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $resolution->resolution),
            'httpStatus' => 204,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get the resolution filters
     * @param $sanitizedQueryParams
     * @return array
     */
    private function getResolutionFilter($sanitizedQueryParams): array
    {
        return $this->gridRenderFilter([
            'enabled' => $sanitizedQueryParams->getInt('enabled', ['default' => -1]),
            'resolutionId' => $sanitizedQueryParams->getInt('resolutionId'),
            'resolution' => $sanitizedQueryParams->getString('resolution'),
            'keyword' => $sanitizedQueryParams->getString('keyword'),
            'partialResolution' => $sanitizedQueryParams->getString('partialResolution'),
            'width' => $sanitizedQueryParams->getInt('width'),
            'height' => $sanitizedQueryParams->getInt('height'),
            'orientation' => $sanitizedQueryParams->getString('orientation')
        ], $sanitizedQueryParams);
    }
}
