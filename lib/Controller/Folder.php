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
    public function __construct(FolderFactory $folderFactory)
    {
        $this->folderFactory = $folderFactory;
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
        $this->getState()->template = 'folders-page';
        $this->getState()->setData([]);

        return $this->render($request, $response);
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
     *  @SWG\Parameter(
     *         name="folderId",
     *         in="path",
     *         description="Show usage details for the specified Folder Id",
     *         type="integer",
     *         required=false
     *     ),
     *  @SWG\Parameter(
     *        name="gridView",
     *        in="query",
     *        description="Flag (0, 1), Show Folders in a standard grid response",
     *        type="integer",
     *        required=false
     *     ),
     *  @SWG\Parameter(
     *       name="folderId",
     *       in="query",
     *       description="Use with gridView, Filter by Folder Id",
     *       type="integer",
     *       required=false
     *    ),
     *   @SWG\Parameter(
     *       name="folderName",
     *       in="query",
     *       description="Use with gridView, Filter by Folder name",
     *       type="string",
     *       required=false
     *    ),
     *   @SWG\Parameter(
     *       name="exactFolderName",
     *       in="query",
     *       description="Use with gridView, Filter by exact Folder name match",
     *       type="integer",
     *       required=false
     *    ),
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
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response, $folderId = null)
    {
        $params = $this->getSanitizer($request->getParams());
        if ($params->getInt('gridView') === 1) {
            $folders = $this->folderFactory->query($this->gridRenderSort($params), $this->gridRenderFilter([
                'folderName' => $params->getString('folderName'),
                'folderId' => $params->getInt('folderId'),
                'exactFolderName' => $params->getInt('exactFolderName'),
            ], $params));

            $this->getState()->template = 'grid';
            $this->getState()->recordsTotal = $this->folderFactory->countLast();
            $this->getState()->setData($folders);

            return $this->render($request, $response);
        } else if ($params->getString('folderName') !== null) {
            // Search all folders by name
            $folders = $this->folderFactory->query($this->gridRenderSort($params), $this->gridRenderFilter([
                'folderName' => $params->getString('folderName'),
                'exactFolderName' => $params->getInt('exactFolderName'),
            ], $params));

            return $response->withJson($folders);
        } else if ($folderId !== null) {
            // Should we return information for a specific folder?
            $folder = $this->folderFactory->getById($folderId);

            $this->decorateWithButtons($folder);
            $this->folderFactory->decorateWithHomeFolderCount($folder);
            $this->folderFactory->decorateWithSharing($folder);
            $this->folderFactory->decorateWithUsage($folder);

            return $response->withJson($folder);
        } else {
            // Show a tree view of all folders.
            $rootFolder = $this->folderFactory->getById(1);

            // homeFolderId,
            // do we show tree for current user
            // or a specified user?
            $homeFolderId = ($params->getInt('homeFolderId') !== null)
                    ? $params->getInt('homeFolderId')
                    : $this->getUser()->homeFolderId;
            
            $this->buildTreeView($rootFolder, $homeFolderId);
            return $response->withJson([$rootFolder]);
        }
    }

    /**
     * @param \Xibo\Entity\Folder $folder
     * @param int $homeFolderId
     * @throws InvalidArgumentException
     */
    private function buildTreeView(\Xibo\Entity\Folder $folder, int $homeFolderId)
    {
        // Set the folder type
        $folder->type = '';
        if ($folder->isRoot === 1) {
            $folder->type = 'root';
        }

        if ($homeFolderId === $folder->id) {
            $folder->type = 'home';
        }

        if (!empty($folder->children)) {
            $children = array_filter(explode(',', $folder->children));
        } else {
            $children = [];
        }
        $childrenDetails = [];

        foreach ($children as $childId) {
            try {
                $child = $this->folderFactory->getById($childId);

                if ($child->children != null) {
                    $this->buildTreeView($child, $homeFolderId);
                }

                if (!$this->getUser()->checkViewable($child)) {
                    $child->text = __('Private Folder');
                    $child->type = 'disabled';
                }

                if ($homeFolderId === $child->id) {
                    $child->type = 'home';
                }

                $childrenDetails[] = $child;
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

        if ($folder->isHome()) {
            throw new InvalidArgumentException(
                __('Cannot remove Folder set as home Folder for a user'),
                'folderId',
                __('Change home Folder for Users using this Folder before deleting')
            );
        }

        if ($folder->id == $this->getConfig()->getSetting('DISPLAY_DEFAULT_FOLDER')) {
            throw new InvalidArgumentException(
                __('Cannot remove Folder set as default Folder for new Displays'),
                'folderId',
                __('Change Default Folder for new Displays before deleting')
            );
        }

        // Check if the folder is in use
        $this->folderFactory->decorateWithUsage($folder);
        $usage = $folder->getUnmatchedProperty('usage');

        // Prevent deletion if the folder has any usage
        if (!empty($usage)) {
            $usageDetails = [];

            // Loop through usage data and construct the formatted message
            foreach ($usage as $item) {
                $usageDetails[] = $item['type'] . ' (' . $item['count'] . ')';
            }

            throw new InvalidArgumentException(
                __('Cannot remove Folder with content: ' . implode(', ', $usageDetails)),
                'folderId',
                __('Reassign objects from this Folder before deleting.')
            );
        }

        try {
            $folder->delete();
        } catch (\Exception $exception) {
            $this->getLog()->debug('Folder delete failed with message: ' . $exception->getMessage());
            throw new InvalidArgumentException(
                __('Cannot remove Folder with content'),
                'folderId',
                __('Reassign objects from this Folder before deleting.')
            );
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
        $folder = $this->folderFactory->getById($folderId);
        $this->decorateWithButtons($folder);

        return $response->withJson($folder->buttons);
    }

    private function decorateWithButtons(\Xibo\Entity\Folder $folder)
    {
        $user = $this->getUser();

        if ($user->featureEnabled('folder.add')
            && $user->checkViewable($folder)
            && (!$folder->isRoot() || $user->isSuperAdmin())
        ) {
            $folder->buttons['create'] = true;
        }

        $featureModify = $user->featureEnabled('folder.modify');
        if ($featureModify
            && $user->checkEditable($folder)
            && !$folder->isRoot()
            && ($this->getUser()->isSuperAdmin() || $folder->getId() !== $this->getUser()->homeFolderId)
        ) {
            $folder->buttons['modify'] = true;
        }

        if ($featureModify
            && $user->checkDeleteable($folder)
            && !$folder->isRoot()
            && ($this->getUser()->isSuperAdmin() || $folder->getId() !== $this->getUser()->homeFolderId)
        ) {
            $folder->buttons['delete'] = true;
        }

        if ($user->isSuperAdmin() && !$folder->isRoot()) {
            $folder->buttons['share'] = true;
        }

        if (!$folder->isRoot() && $user->checkViewable($folder) && $user->featureEnabled('folder.modify')) {
            $folder->buttons['move'] = true;
        }
    }

    public function moveForm(Request $request, Response $response, $folderId)
    {
        $folder = $this->folderFactory->getById($folderId, 0);

        if (!$this->getUser()->checkEditable($folder)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'folder-form-move';
        $this->getState()->setData([
            'folder' => $folder,
            'deletable' => $this->getUser()->checkDeleteable($folder)
        ]);

        return $this->render($request, $response);
    }

    public function move(Request $request, Response $response, $folderId)
    {
        $params = $this->getSanitizer($request->getParams());
        $folder = $this->folderFactory->getById($folderId);
        $newParentFolder = $this->folderFactory->getById($params->getInt('folderId'), 0);

        if (!$this->getUser()->checkEditable($folder)
            || $folder->isRoot()
            || !$this->getUser()->checkViewable($newParentFolder)
        ) {
            throw new AccessDeniedException();
        }

        if ($folder->id === $params->getInt('folderId')) {
            throw new InvalidArgumentException(
                __('Please select different folder, cannot move Folder to the same Folder')
            );
        }

        if ($folder->isTheSameBranch($newParentFolder->getId())) {
            throw new InvalidArgumentException(
                __('Please select different folder, cannot move Folder inside of one of its sub-folders')
            );
        }

        if ($folder->parentId === $newParentFolder->getId() && $params->getCheckbox('merge') !== 1) {
            throw new InvalidArgumentException(__('This Folder is already a sub-folder of the selected Folder, if you wish to move its content to the parent Folder, please check the merge checkbox.'));//phpcs:ignore
        }

        // if we need to merge contents of the folder, dispatch an event that will move every object inside the folder
        // to the new folder, any sub-folders will be moved to the new parent folder keeping the tree structure.
        if ($params->getCheckbox('merge') === 1) {
            $event = new \Xibo\Event\FolderMovingEvent($folder, $newParentFolder, true);
            $this->getDispatcher()->dispatch($event, $event::$NAME);

            // after moving event is done, we should be able to safely delete the original folder
            $folder = $this->folderFactory->getById($folderId, 0);
            $folder->load();
            $folder->delete();
        } else {
            // if we just want to move the Folder to new parent, we move folder and its sub-folders to the new parent
            // changing the permissionsFolderId as well if needed.
            $folder->updateFoldersAfterMove($folder->parentId, $newParentFolder->getId());
        }

        return $this->render($request, $response);
    }
}
