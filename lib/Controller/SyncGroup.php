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
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class SyncGroup
 * @package Xibo\Controller
 */
class SyncGroup extends Base
{
    public function __construct(
        private readonly SyncGroupFactory $syncGroupFactory,
        private readonly FolderFactory $folderFactory
    ) {
    }

    #[OA\Get(
        path: '/syncgroups',
        operationId: 'syncGroupSearch',
        description: 'Search for Sync Groups viewable by this user',
        summary: 'Search Sync Groups',
        tags: ['syncGroup']
    )]
    #[OA\Parameter(
        name: 'syncGroupId',
        description: 'Filter by syncGroup Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'Filter by partial Sync Group name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by Sync Group name or ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'ownerId',
        description: 'Filter by Owner ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'folderId',
        description: 'Filter by Folder ID',
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
                'syncGroupId',
                'name',
                'owner',
                'createdDt',
                'modifiedDt',
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
            items: new OA\Items(ref: '#/components/schemas/SyncGroup')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $syncGroups = $this->syncGroupFactory->query(
            $this->gridRenderSort($parsedQueryParams, $this->isJson($request)),
            $this->getSyncGroupFilters($parsedQueryParams)
        );

        foreach ($syncGroups as $syncGroup) {
            $this->decorateSyncGroupProperties($syncGroup);
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->syncGroupFactory->countLast())
            ->withJson($syncGroups);
    }

    #[OA\Get(
        path: '/syncgroup/{syncGroupId}',
        operationId: 'syncGroupSearchById',
        description: 'Get the Sync Group object specified by the provided syncGroupId',
        summary: 'Search Sync Groups by ID',
        tags: ['syncGroup']
    )]
    #[OA\Parameter(
        name: 'syncGroupId',
        description: 'Numeric ID of the Sync Group to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/SyncGroup')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $syncGroup = $this->syncGroupFactory->getById($id, false);

        $this->decorateSyncGroupProperties($syncGroup);

        return $response
            ->withStatus(200)
            ->withJson($syncGroup);
    }

    #[OA\Post(
        path: '/syncgroup/add',
        operationId: 'syncGroupAdd',
        description: 'Add a new Sync Group to the CMS',
        summary: 'Add a Sync Group',
        tags: ['syncGroup']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', description: 'The Sync Group Name', type: 'string'),
                    new OA\Property(
                        property: 'syncPublisherPort',
                        description: 'The publisher port number on which sync group members will communicate - default 9590', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'syncSwitchDelay',
                        description: 'The delay (in ms) when displaying the changes in content - default 750', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'syncVideoPauseDelay',
                        description: 'The delay (in ms) before unpausing the video on start - default 100', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
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
                description: 'Location of the new SyncGroup',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/SyncGroup')
    )]
    /**
     * Adds a Sync Group
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
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
        return $response->withStatus(201)->withJson($syncGroup);
    }

    #[OA\Post(
        path: '/syncgroup/{syncGroupId}/members',
        operationId: 'syncGroupMembers',
        description: 'Adds the provided Displays to the Sync Group',
        summary: 'Assign one or more Displays to a Sync Group',
        tags: ['syncGroup']
    )]
    #[OA\Parameter(
        name: 'syncGroupId',
        description: 'The Sync Group to assign to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['displayId'],
                properties: [
                    new OA\Property(
                        property: 'displayId',
                        description: 'The Display Ids to assign',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'unassignDisplayId',
                        description: 'An optional array of Display IDs to unassign',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
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
        return $response->withStatus(204);
    }

    #[OA\Post(
        path: '/syncgroup/{syncGroupId}/edit',
        operationId: 'syncGroupEdit',
        description: 'Edit an existing Sync Group',
        summary: 'Edit a Sync Group',
        tags: ['syncGroup']
    )]
    #[OA\Parameter(
        name: 'syncGroupId',
        description: 'The Sync Group to edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name', 'leadDisplayId'],
                properties: [
                    new OA\Property(property: 'name', description: 'The Sync Group Name', type: 'string'),
                    new OA\Property(
                        property: 'syncPublisherPort',
                        description: 'The publisher port number on which sync group members will communicate - default 9590', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'syncSwitchDelay',
                        description: 'The delay (in ms) when displaying the changes in content - default 750', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'syncVideoPauseDelay',
                        description: 'The delay (in ms) before unpausing the video on start - default 100', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'leadDisplayId',
                        description: 'The ID of the Display that belongs to this Sync Group and should act as a Lead Display', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
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
                description: 'Location of the new SyncGroup',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/SyncGroup')
    )]
    /**
     * Edits a Sync Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
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
        return $response
            ->withStatus(200)
            ->withJson($syncGroup);
    }

    #[OA\Delete(
        path: '/syncgroup/{syncGroupId}/delete',
        operationId: 'syncGroupDelete',
        description: 'Delete an existing Sync Group identified by its Id',
        summary: 'Delete a Sync Group',
        tags: ['syncGroup']
    )]
    #[OA\Parameter(
        name: 'syncGroupId',
        description: 'The syncGroupId to delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
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

    #[OA\Get(
        path: '/syncgroup/{syncGroupId}/displays',
        operationId: 'syncGroupDisplays',
        description: 'Get the Display members of the specified Sync Group',
        summary: 'Get Sync Group members',
        tags: ['syncGroup']
    )]
    #[OA\Parameter(
        name: 'syncGroupId',
        description: 'The ID of the Sync Group to get members for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'eventId',
        description: 'Filter by event ID - return will include Layouts Ids scheduled against each group member', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/SyncGroup')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
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

        return $response
            ->withStatus(200)
            ->withJson(['displays' => $displays]);
    }

    private function getSyncGroupFilters(SanitizerInterface $sanitizedParams): array
    {
        return $this->gridRenderFilter([
            'syncGroupId'   => $sanitizedParams->getInt('syncGroupId'),
            'name'          => $sanitizedParams->getString('name'),
            'keyword'       => $sanitizedParams->getString('keyword'),
            'folderId'      => $sanitizedParams->getInt('folderId'),
            'ownerId'       => $sanitizedParams->getInt('ownerId'),
            'leadDisplayId' => $sanitizedParams->getInt('leadDisplayId'),
        ], $sanitizedParams);
    }

    /**
     * @param \Xibo\Entity\SyncGroup $syncGroup
     * @return void
     * @throws InvalidArgumentException
     */
    private function decorateSyncGroupProperties(\Xibo\Entity\SyncGroup $syncGroup): void
    {
        // User permissions
        $syncGroup->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($syncGroup));
        if (!empty($syncGroup->leadDisplayId)) {
            try {
                $display = $this->syncGroupFactory->getLeadDisplay($syncGroup->leadDisplayId);
                $syncGroup->leadDisplay = $display->display;
            } catch (NotFoundException $exception) {
                $this->getLog()->error(sprintf(
                    'Lead Display %d not found for %s',
                    $syncGroup->leadDisplayId,
                    $syncGroup->name
                ));
            }
        }
    }
}
