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

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SyncGroup
 * @package Xibo\Controller
 */
class SyncGroup extends Base
{
    private SyncGroupFactory $syncGroupFactory;
    private FolderFactory $folderFactory;

    public function __construct(
        SyncGroupFactory $syncGroupFactory,
        FolderFactory $folderFactory
    ) {
        $this->syncGroupFactory = $syncGroupFactory;
        $this->folderFactory = $folderFactory;
    }

    /**
     * Sync Group Page Render
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'syncgroup-page';

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/syncgroups",
     *  summary="Get Sync Groups",
     *  tags={"syncGroup"},
     *  operationId="syncGroupSearch",
     *  @SWG\Parameter(
     *      name="syncGroupId",
     *      in="query",
     *      description="Filter by syncGroup Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="query",
     *      description="Filter by syncGroup Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="query",
     *      description="Filter by Owner ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="query",
     *      description="Filter by Folder ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="a successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/SyncGroup")
     *      ),
     *      @SWG\Header(
     *          header="X-Total-Count",
     *          description="The total number of records",
     *          type="integer"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws ControllerNotImplemented
     * @throws InvalidArgumentException
     */
    public function grid(Request $request, Response $response): Response|\Psr\Http\Message\ResponseInterface
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'syncGroupId' => $parsedQueryParams->getInt('syncGroupId'),
            'name' => $parsedQueryParams->getString('name'),
            'folderId' => $parsedQueryParams->getInt('folderId'),
            'ownerId' => $parsedQueryParams->getInt('ownerId'),
            'leadDisplayId' => $parsedQueryParams->getInt('leadDisplayId')
        ];

        $syncGroups = $this->syncGroupFactory->query(
            $this->gridRenderSort($parsedQueryParams),
            $this->gridRenderFilter($filter, $parsedQueryParams)
        );

        foreach ($syncGroups as $syncGroup) {
            if (!empty($syncGroup->leadDisplayId)) {
                try {
                    $display = $this->syncGroupFactory->getLeadDisplay($syncGroup->leadDisplayId);
                    $syncGroup->leadDisplay = $display->display;
                } catch (NotFoundException $exception) {
                    $this->getLog()->error(
                        sprintf(
                            'Lead Display %d not found for %s',
                            $syncGroup->leadDisplayId,
                            $syncGroup->name
                        )
                    );
                }
            }

            if ($this->isApi($request)) {
                continue;
            }

            $syncGroup->includeProperty('buttons');

            if ($this->getUser()->featureEnabled('display.syncModify')
                && $this->getUser()->checkEditable($syncGroup)
            ) {
                // Edit
                $syncGroup->buttons[] = [
                    'id' => 'syncgroup_button_group_edit',
                    'url' => $this->urlFor($request, 'syncgroup.form.edit', ['id' => $syncGroup->syncGroupId]),
                    'text' => __('Edit')
                ];
                // Group Members
                $syncGroup->buttons[] = [
                    'id' => 'syncgroup_button_group_members',
                    'url' => $this->urlFor($request, 'syncgroup.form.members', ['id' => $syncGroup->syncGroupId]),
                    'text' => __('Members')
                ];
                $syncGroup->buttons[] = ['divider' => true];

                // Delete
                $syncGroup->buttons[] = [
                    'id' => 'syncgroup_button_group_delete',
                    'url' => $this->urlFor($request, 'syncgroup.form.delete', ['id' => $syncGroup->syncGroupId]),
                    'text' => __('Delete')
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->syncGroupFactory->countLast();
        $this->getState()->setData($syncGroups);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function addForm(Request $request, Response $response): Response|ResponseInterface
    {
        $this->getState()->template = 'syncgroup-form-add';

        return $this->render($request, $response);
    }

    /**
     * Adds a Sync Group
     * @SWG\Post(
     *  path="/syncgroup/add",
     *  operationId="syncGroupAdd",
     *  tags={"syncGroup"},
     *  summary="Add a Sync Group",
     *  description="Add a new Sync Group to the CMS",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Sync Group Name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="syncPublisherPort",
     *      in="formData",
     *      description="The publisher port number on which sync group members will communicate - default 9590",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new DisplayGroup",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        if (!$this->getUser()->featureEnabled('display.syncAdd')) {
            throw new AccessDeniedException();
        }

        $params = $this->getSanitizer($request->getParams());

        // Folders
        $folderId = $params->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);

        $syncGroup = $this->syncGroupFactory->createEmpty();
        $syncGroup->name = $params->getString('name');
        $syncGroup->ownerId = $this->getUser()->userId;
        $syncGroup->syncPublisherPort = $params->getInt('syncPublisherPort');
        $syncGroup->syncSwitchDelay = $params->getInt('syncSwitchDelay');
        $syncGroup->syncVideoPauseDelay = $params->getInt('syncVideoPauseDelay');
        $syncGroup->folderId = $folder->getId();
        $syncGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        $syncGroup->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $syncGroup->name),
            'id' => $syncGroup->syncGroupId,
            'data' => $syncGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function membersForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($syncGroup)) {
            throw new AccessDeniedException();
        }

        // Displays in Group
        $displaysAssigned = $syncGroup->getSyncGroupMembers();

        $this->getState()->template = 'syncgroup-form-members';
        $this->getState()->setData([
            'syncGroup' => $syncGroup,
            'extra' => [
                'displaysAssigned' => $displaysAssigned,
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/syncgroup/{syncGroupId}/members",
     *  operationId="syncGroupMembers",
     *  tags={"syncGroup"},
     *  summary="Assign one or more Displays to a Sync Group",
     *  description="Adds the provided Displays to the Sync Group",
     *  @SWG\Parameter(
     *      name="syncGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Sync Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      type="array",
     *      in="formData",
     *      description="The Display Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignDisplayId",
     *      in="formData",
     *      description="An optional array of Display IDs to unassign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function members(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($syncGroup)) {
            throw new AccessDeniedException();
        }

        // Support both an array and a single int.
        $displays = $sanitizedParams->getParam('displayId');
        if (is_numeric($displays)) {
            $displays = [$sanitizedParams->getInt('displayId')];
        } else {
            $displays = $sanitizedParams->getIntArray('displayId', ['default' => []]);
        }

        $syncGroup->setMembers($displays);

        // Have we been provided with unassign id's as well?
        $unSetDisplays = $sanitizedParams->getParam('unassignDisplayId');
        if (is_numeric($unSetDisplays)) {
            $unSetDisplays = [$sanitizedParams->getInt('unassignDisplayId')];
        } else {
            $unSetDisplays = $sanitizedParams->getIntArray('unassignDisplayId', ['default' => []]);
        }

        $syncGroup->unSetMembers($unSetDisplays);
        $syncGroup->modifiedBy = $this->getUser()->userId;

        if (empty($syncGroup->getSyncGroupMembers()) ||
            in_array($syncGroup->leadDisplayId, $unSetDisplays)
        ) {
            $syncGroup->leadDisplayId = null;
        }

        $syncGroup->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Displays assigned to %s'), $syncGroup->name),
            'id' => $syncGroup->syncGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function editForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($syncGroup)) {
            throw new AccessDeniedException();
        }

        $leadDisplay = null;

        if (!empty($syncGroup->leadDisplayId)) {
            $leadDisplay = $this->syncGroupFactory->getLeadDisplay($syncGroup->leadDisplayId);
        }

        $this->getState()->template = 'syncgroup-form-edit';
        $this->getState()->setData([
            'syncGroup' => $syncGroup,
            'leadDisplay' => $leadDisplay,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edits a Sync Group
     * @SWG\Post(
     *  path="/syncgroup/{syncGroupId}/edit",
     *  operationId="syncGroupEdit",
     *  tags={"syncGroup"},
     *  summary="Edit a Sync Group",
     *  description="Edit an existing Sync Group",
     *  @SWG\Parameter(
     *      name="syncGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Sync Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Sync Group Name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="syncPublisherPort",
     *      in="formData",
     *      description="The publisher port number on which sync group members will communicate - default 9590",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="syncSwitchDelay",
     *      in="formData",
     *      description="The delay (in ms) when displaying the changes in content - default 750",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="syncVideoPauseDelay",
     *      in="formData",
     *      description="The delay (in ms) before unpausing the video on start - default 100",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="leadDisplayId",
     *      in="formData",
     *      description="The ID of the Display that belongs to this Sync Group and should act as a Lead Display",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new DisplayGroup",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);
        $params = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($syncGroup)) {
            throw new AccessDeniedException();
        }

        // Folders
        $folderId = $params->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);

        $syncGroup->name = $params->getString('name');
        $syncGroup->syncPublisherPort = $params->getInt('syncPublisherPort');
        $syncGroup->syncSwitchDelay = $params->getInt('syncSwitchDelay');
        $syncGroup->syncVideoPauseDelay = $params->getInt('syncVideoPauseDelay');
        $syncGroup->leadDisplayId = $params->getInt('leadDisplayId');
        $syncGroup->modifiedBy = $this->getUser()->userId;
        $syncGroup->folderId = $folder->getId();
        $syncGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        $syncGroup->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $syncGroup->name),
            'id' => $syncGroup->syncGroupId,
            'data' => $syncGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function deleteForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($syncGroup)) {
            throw new AccessDeniedException();
        }

        // Set the form
        $this->getState()->template = 'syncgroup-form-delete';
        $this->getState()->setData([
            'syncGroup' => $syncGroup,
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Delete(
     *  path="/syncgroup/{syncGroupId}/delete",
     *  operationId="syncGroupDelete",
     *  tags={"syncGroup"},
     *  summary="Delete a Sync Group",
     *  description="Delete an existing Sync Group identified by its Id",
     *  @SWG\Parameter(
     *      name="syncGroupId",
     *      type="integer",
     *      in="path",
     *      description="The syncGroupId to delete",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($syncGroup)) {
            throw new AccessDeniedException();
        }

        $syncGroup->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $syncGroup->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/syncgroup/{syncGroupId}/displays",
     *  summary="Get members of this sync group",
     *  tags={"syncGroup"},
     *  operationId="syncGroupDisplays",
     *  @SWG\Parameter(
     *      name="syncGroupId",
     *      type="integer",
     *      in="path",
     *      description="The syncGroupId to delete",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="eventId",
     *      in="query",
     *      description="Filter by event ID - return will include Layouts Ids scheduled against each group member",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="a successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/SyncGroup")
     *      ),
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function fetchDisplays(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id);
        $params = $this->getSanitizer($request->getParams());
        $displays = [];

        if (!empty($params->getInt('eventId'))) {
            $syncGroupMembers = $syncGroup->getGroupMembersForForm();
            foreach ($syncGroupMembers as $display) {
                $layoutId = $syncGroup->getLayoutIdForDisplay(
                    $params->getInt('eventId'),
                    $display['displayId']
                );
                $display['layoutId'] = $layoutId;
                $displays[] = $display;
            }
        } else {
            $displays = $syncGroup->getGroupMembersForForm();
        }

        $this->getState()->setData([
            'displays' => $displays
        ]);

        return $this->render($request, $response);
    }
}