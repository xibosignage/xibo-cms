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

    /**
     * Resolution Grid
     *
     * @SWG\Get(
     *  path="/resolution",
     *  operationId="resolutionSearch",
     *  tags={"resolution"},
     *  summary="Resolution Search",
     *  description="Search Resolutions this user has access to",
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="query",
     *      description="Filter by Resolution Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="resolution",
     *      in="query",
     *      description="Filter by Resolution Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="partialResolution",
     *      in="query",
     *      description="Filter by Partial Resolution Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enabled",
     *      in="query",
     *      description="Filter by Enabled",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="width",
     *      in="query",
     *      description="Filter by Resolution width",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="height",
     *      in="query",
     *      description="Filter by Resolution height",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Resolution")
     *      )
     *  )
     * )
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

    /**
     * Add Resolution
     *
     * @SWG\Post(
     *  path="/resolution",
     *  operationId="resolutionAdd",
     *  tags={"resolution"},
     *  summary="Add Resolution",
     *  description="Add new Resolution",
     *  @SWG\Parameter(
     *      name="resolution",
     *      in="formData",
     *      description="A name for the Resolution",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="width",
     *      in="formData",
     *      description="The Display Width of the Resolution",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="height",
     *      in="formData",
     *      description="The Display Height of the Resolution",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Resolution"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
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
     * @SWG\Put(
     *  path="/resolution/{resolutionId}",
     *  operationId="resolutionEdit",
     *  tags={"resolution"},
     *  summary="Edit Resolution",
     *  description="Edit new Resolution",
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="path",
     *      description="The Resolution ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="resolution",
     *      in="formData",
     *      description="A name for the Resolution",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="width",
     *      in="formData",
     *      description="The Display Width of the Resolution",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="height",
     *      in="formData",
     *      description="The Display Height of the Resolution",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Resolution")
     *  )
     * )
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
     * @SWG\Delete(
     *  path="/resolution/{resolutionId}",
     *  operationId="resolutionDelete",
     *  tags={"resolution"},
     *  summary="Delete Resolution",
     *  description="Delete Resolution",
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="path",
     *      description="The Resolution ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
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
