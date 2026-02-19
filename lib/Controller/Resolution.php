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
        // Show enabled
        $filter = [
            'enabled' => $sanitizedQueryParams->getInt('enabled', ['default' => -1]),
            'resolutionId' => $sanitizedQueryParams->getInt('resolutionId'),
            'resolution' => $sanitizedQueryParams->getString('resolution'),
            'partialResolution' => $sanitizedQueryParams->getString('partialResolution'),
            'width' => $sanitizedQueryParams->getInt('width'),
            'height' => $sanitizedQueryParams->getInt('height'),
            'orientation' => $sanitizedQueryParams->getString('orientation')
        ];

        $resolutions = $this->resolutionFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter($filter, $sanitizedQueryParams));

        foreach ($resolutions as $resolution) {
            /* @var \Xibo\Entity\Resolution $resolution */

            if ($this->isApi($request))
                break;

            $resolution->includeProperty('buttons');

            if ($this->getUser()->featureEnabled('resolution.modify')
                && $this->getUser()->checkEditable($resolution)
            ) {
                // Edit Button
                $resolution->buttons[] = array(
                    'id' => 'resolution_button_edit',
                    'url' => $this->urlFor($request,'resolution.edit.form', ['id' => $resolution->resolutionId]),
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->featureEnabled('resolution.modify')
                && $this->getUser()->checkDeleteable($resolution)
            ) {
                // Delete Button
                $resolution->buttons[] = array(
                    'id' => 'resolution_button_delete',
                    'url' => $this->urlFor($request,'resolution.delete.form', ['id' => $resolution->resolutionId]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($resolutions);
        $this->getState()->recordsTotal = $this->resolutionFactory->countLast();

        return $this->render($request, $response);
    }

    /**
     * Resolution Add
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'resolution-form-add';
        return $this->render($request, $response);
    }

    /**
     * Resolution Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function editForm(Request $request, Response $response, $id)
    {
        $resolution = $this->resolutionFactory->getById($id);

        if (!$this->getUser()->checkEditable($resolution)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'resolution-form-edit';
        $this->getState()->setData([
            'resolution' => $resolution,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Resolution Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $resolution = $this->resolutionFactory->getById($id);

        if (!$this->getUser()->checkEditable($resolution)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'resolution-form-delete';
        $this->getState()->setData([
            'resolution' => $resolution,
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/resolution',
        operationId: 'resolutionAdd',
        description: 'Add new Resolution',
        summary: 'Add Resolution',
        tags: ['resolution']
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
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
                ],
                required: ['resolution', 'width', 'height']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Resolution'),
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ]
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

        /* @var \Xibo\Entity\Resolution $resolution */
        $resolution = $this->resolutionFactory->create($sanitizedParams->getString('resolution'),
            $sanitizedParams->getInt('width'),
            $sanitizedParams->getInt('height'));

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
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
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
                ],
                required: ['resolution', 'width', 'height']
            )
        ),
        required: true
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
}
