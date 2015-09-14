<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
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

use baseDAO;
use Kit;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\ResolutionFactory;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;


class Resolution extends Base
{
    /**
     * Display the Resolution Page
     */
    function displayPage()
    {
        if ($this->getSession()->get('resolution', 'Filter') == 1) {
            $pinned = 1;
            $enabled = $this->getSession()->get('resolution', 'filterEnabled');
        } else {
            $enabled = 1;
            $pinned = 0;
        }

        $data = [
            'defaults' => [
                'enabled' => $enabled,
                'filterPinned' => $pinned
            ]
        ];

        $this->getState()->template = 'resolution-page';
        $this->getState()->setData($data);
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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Resolution")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $this->getSession()->set('resolution', 'ResolutionFilter', Sanitize::getCheckbox('XiboFilterPinned'));

        // Show enabled
        $filter = [
            'enabled' => $this->getSession()->set('resolution', 'filterEnabled', Sanitize::getInt('filterEnabled', -1)),
            'resolutionId' => Sanitize::getInt('resolutionId')
        ];

        $resolutions = ResolutionFactory::query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($resolutions as $resolution) {
            /* @var \Xibo\Entity\Resolution $resolution */

            if ($this->isApi())
                break;

            $resolution->includeProperty('buttons');

            // Edit Button
            $resolution->buttons[] = array(
                'id' => 'resolution_button_edit',
                'url' => $this->urlFor('resolution.edit.form', ['id' => $resolution->resolutionId]),
                'text' => __('Edit')
            );

            // Delete Button
            $resolution->buttons[] = array(
                'id' => 'resolution_button_delete',
                'url' => $this->urlFor('resolution.delete.form', ['id' => $resolution->resolutionId]),
                'text' => __('Delete')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($resolutions);
        $this->getState()->recordsTotal = ResolutionFactory::countLast();
    }

    /**
     * Resolution Add
     */
    function addForm()
    {
        $this->getState()->template = 'resolution-form-add';
        $this->getState()->setData([
            'help' => Help::Link('Resolution', 'Add')
        ]);
    }

    /**
     * Resolution Edit Form
     * @param int $resolutionId
     */
    function editForm($resolutionId)
    {
        $resolution = ResolutionFactory::getById($resolutionId);

        if (!$this->getUser()->checkEditable($resolution))
            throw new AccessDeniedException();

        $this->getState()->template = 'resolution-form-edit';
        $this->getState()->setData([
            'resolution' => $resolution,
            'help' => Help::Link('Resolution', 'Edit')
        ]);
    }

    /**
     * Resolution Delete Form
     * @param int $resolutionId
     */
    function deleteForm($resolutionId)
    {
        $resolution = ResolutionFactory::getById($resolutionId);

        if (!$this->getUser()->checkEditable($resolution))
            throw new AccessDeniedException();

        $this->getState()->template = 'resolution-form-delete';
        $this->getState()->setData([
            'resolution' => $resolution,
            'help' => Help::Link('Resolution', 'Delete')
        ]);
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
     */
    function add()
    {
        $resolution = new \Xibo\Entity\Resolution();
        $resolution->resolution = Sanitize::getString('resolution');
        $resolution->width = Sanitize::getInt('width');
        $resolution->height = Sanitize::getInt('height');
        $resolution->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $resolution->resolution),
            'id' => $resolution->resolutionId,
            'data' => $resolution
        ]);
    }

    /**
     * Edit Resolution
     * @param int $resolutionId
     *
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
    function edit($resolutionId)
    {
        $resolution = ResolutionFactory::getById($resolutionId);

        if (!$this->getUser()->checkEditable($resolution))
            throw new AccessDeniedException();

        $resolution->resolution = Sanitize::getString('resolution');
        $resolution->width = Sanitize::getInt('width');
        $resolution->height = Sanitize::getInt('height');
        $resolution->enabled = Sanitize::getCheckbox('enabled');
        $resolution->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $resolution->resolution),
            'id' => $resolution->resolutionId,
            'data' => $resolution
        ]);
    }

    /**
     * Delete Resolution
     * @param int $resolutionId
     *
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
    function delete($resolutionId)
    {
        $resolution = ResolutionFactory::getById($resolutionId);

        if (!$this->getUser()->checkDeleteable($resolution))
            throw new AccessDeniedException();

        $resolution->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $resolution->resolution),
        ]);
    }
}
