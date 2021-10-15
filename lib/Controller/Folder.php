<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
use Xibo\Factory\FolderFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class Folder extends Base
{
    /**
     * @var FolderFactory
     */
    private $folderFactory;

    /**
     * Set common dependencies.
     * @param FolderFactory $folderFactory
     */
    public function __construct($folderFactory)
    {
        $this->folderFactory = $folderFactory;
    }

    /**
     * Returns JSON representation of the Folder tree
     *
     * @SWG\Get(
     *  path="/folders",
     *  operationId="folderSearch",
     *  tags={"folder"},
     *  summary="Search Folders",
     *  description="Returns JSON representation of the Folder tree",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Folder")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response)
    {
        $parsedParams = $this->getSanitizer($request->getParams());
        $treeJson = [];

        $folders = $this->folderFactory->query($this->gridRenderSort($parsedParams), $this->gridRenderFilter([
            'folderId' => $parsedParams->getInt('folderId'),
            'folderName' => $parsedParams->getString('folderName'),
            'isRoot' => $parsedParams->getInt('isRoot'),
            'includeRoot' => 1
        ], $parsedParams));

        foreach ($folders as $folder) {
            if ($folder->id === 1) {
                $folder->text = 'Root Folder';
                $folder->a_attr['title'] = __("Right click a Folder for further Options");
                $this->buildTreeView($folder);
                array_push($treeJson, $folder);
            }
        }

        return $response->withJson($treeJson);
    }

    /**
     * @param \Xibo\Entity\Folder $folder
     */
    private function buildTreeView(&$folder)
    {
        $children = array_filter(explode(',', $folder->children));
        $childrenDetails = [];

        foreach ($children as $childId) {
            try {
                $child = $this->folderFactory->getById($childId, 1);

                if ($child->children != null) {
                    $this->buildTreeView($child);
                }

                if (!$this->getUser()->checkViewable($child)) {
                    $child->text = __('Private Folder');
                    $child->li_attr['disabled'] = true;
                }

                array_push($childrenDetails, $child);
            } catch (NotFoundException $exception) {
                // this should be fine, just log debug message about it.
                $this->getLog()->debug('User does not have permissions to Folder ID ' . $childId);
            }
        }

        $folder->children = $childrenDetails;
    }

    /**
     * Add a new Folder
     *
     * @SWG\Post(
     *  path="/folders",
     *  operationId="folderAdd",
     *  tags={"folder"},
     *  summary="Add Folder",
     *  description="Add a new Folder to the specified parent Folder",
     *  @SWG\Parameter(
     *      name="text",
     *      in="formData",
     *      description="Folder Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="parentId",
     *      in="formData",
     *      description="The ID of the parent Folder, if not provided, Folder will be added under Root Folder",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          @SWG\Items(ref="#/definitions/Folder")
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
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $folder = $this->folderFactory->createEmpty();
        $folder->text = $sanitizedParams->getString('text');
        $folder->parentId = $sanitizedParams->getString('parentId', ['default' => 1]);

        $folder->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $folder->text),
            'id' => $folder->id,
            'data' => $folder
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit existing Folder
     *
     * @SWG\Put(
     *  path="/folders/{folderId}",
     *  operationId="folderEdit",
     *  tags={"folder"},
     *  summary="Edit Folder",
     *  description="Edit existing Folder",
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="path",
     *      description="Folder ID to edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="text",
     *      in="formData",
     *      description="Folder Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          @SWG\Items(ref="#/definitions/Folder")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $folderId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $folderId)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $folder = $this->folderFactory->getById($folderId);

        if ($folder->isRoot === 1) {
            throw new InvalidArgumentException(__('Cannot edit root Folder'), 'isRoot');
        }

        if (!$this->getUser()->checkEditable($folder)) {
            throw new AccessDeniedException();
        }

        $folder->text = $sanitizedParams->getString('text');

        $folder->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $folder->text),
            'id' => $folder->id,
            'data' => $folder
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete existing Folder
     *
     * @SWG\Delete(
     *  path="/folders/{folderId}",
     *  operationId="folderDelete",
     *  tags={"folder"},
     *  summary="Delete Folder",
     *  description="Delete existing Folder",
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="path",
     *      description="Folder ID to edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation",
     *      @SWG\Schema(
     *          @SWG\Items(ref="#/definitions/Folder")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $folderId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function delete(Request $request, Response $response, $folderId)
    {
        $folder = $this->folderFactory->getById($folderId);
        $folder->load();

        if ($folder->isRoot === 1) {
            throw new InvalidArgumentException(__('Cannot remove root Folder'), 'isRoot');
        }

        if (!$this->getUser()->checkDeleteable($folder)) {
            throw new AccessDeniedException();
        }

        try {
            $folder->delete();
        } catch (\Exception $exception) {
            $this->getLog()->debug('Folder delete failed with message: ' . $exception->getMessage());
            throw new InvalidArgumentException(__('Cannot remove Folder with content'), 'folderId', __('Reassign objects from this Folder before deleting.'));
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $folder->text)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $folderId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getContextMenuButtons(Request $request, Response $response, $folderId)
    {
        $user = $this->getUser();
        $folder = $this->folderFactory->getById($folderId);

        $buttons = [];

        if ($user->featureEnabled('folder.add')) {
            $buttons['create'] = true;
        }

        if ($user->featureEnabled('folder.modify') && $user->checkEditable($folder) && !$folder->isRoot()) {
            $buttons['modify'] = true;
        }

        if ($user->featureEnabled('folder.modify') && $user->checkDeleteable($folder) && !$folder->isRoot()) {
            $buttons['delete'] = true;
        }

        if ($user->isSuperAdmin() && !$folder->isRoot()) {
            $buttons['share'] = true;
        }

        return $response->withJson($buttons);
    }
}
