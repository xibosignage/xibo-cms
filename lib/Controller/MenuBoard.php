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
use Xibo\Factory\FolderFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Menu Board Controller
 */
class MenuBoard extends Base
{
    /**
     * Set common dependencies.
     * @param MenuBoardFactory $menuBoardFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct(
        private readonly MenuBoardFactory $menuBoardFactory,
        private readonly FolderFactory $folderFactory
    ) {
    }

    /**
     * Displays the Menu Board Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        // Call to render the template
        $this->getState()->template = 'menuboard-page';

        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/menuboards',
        operationId: 'menuBoardSearch',
        description: 'Search all Menu Boards this user has access to',
        summary: 'Search Menu Boards',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'Filter by Menu board Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'userId',
        description: 'Filter by Owner Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'folderId',
        description: 'Filter by Folder Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'Filter by name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'Filter by code',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/MenuBoard')
        )
    )]
    /**
     * Returns a Grid of Menu Boards
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response): Response
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'menuId' => $parsedParams->getInt('menuId'),
            'userId' => $parsedParams->getInt('userId'),
            'name' => $parsedParams->getString('name'),
            'code' => $parsedParams->getString('code'),
            'folderId' => $parsedParams->getInt('folderId'),
            'logicalOperatorName' => $parsedParams->getString('logicalOperatorName'),
        ];

        $menuBoards = $this->menuBoardFactory->query(
            $this->gridRenderSort($parsedParams),
            $this->gridRenderFilter($filter, $parsedParams)
        );

        foreach ($menuBoards as $menuBoard) {
            if ($this->isApi($request)) {
                continue;
            }

            $menuBoard->includeProperty('buttons');
            $menuBoard->buttons = [];

            if ($this->getUser()->featureEnabled('menuBoard.modify') && $this->getUser()->checkEditable($menuBoard)) {
                $menuBoard->buttons[] = [
                    'id' => 'menuBoard_button_viewcategories',
                    'url' => $this->urlFor($request, 'menuBoard.category.view', ['id' => $menuBoard->menuId]),
                    'class' => 'XiboRedirectButton',
                    'text' => __('View Categories')
                ];

                $menuBoard->buttons[] = [
                    'id' => 'menuBoard_edit_button',
                    'url' => $this->urlFor($request, 'menuBoard.edit.form', ['id' => $menuBoard->menuId]),
                    'text' => __('Edit')
                ];

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $menuBoard->buttons[] = [
                        'id' => 'menuBoard_button_selectfolder',
                        'url' => $this->urlFor($request, 'menuBoard.selectfolder.form', ['id' => $menuBoard->menuId]),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor($request, 'menuBoard.selectfolder', ['id' => $menuBoard->menuId])
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'menuBoard_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $menuBoard->name],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }
            }

            if ($this->getUser()->featureEnabled('menuBoard.modify') && $this->getUser()->checkPermissionsModifyable($menuBoard)) {
                $menuBoard->buttons[] = ['divider' => true];

                // Share button
                $menuBoard->buttons[] = [
                    'id' => 'menuBoard_button_permissions',
                    'url' => $this->urlFor($request, 'user.permissions.form', ['entity' => 'MenuBoard', 'id' => $menuBoard->menuId]),
                    'text' => __('Share'),
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'user.permissions.multi', ['entity' => 'MenuBoard', 'id' => $menuBoard->menuId])
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'menuBoard_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $menuBoard->name],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor($request, 'user.permissions.multi.form', ['entity' => 'MenuBoard'])
                        ],
                        ['name' => 'content-id-name', 'value' => 'menuId']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('menuBoard.modify')
                && $this->getUser()->checkDeleteable($menuBoard)
            ) {
                $menuBoard->buttons[] = ['divider' => true];

                $menuBoard->buttons[] = [
                    'id' => 'menuBoard_delete_button',
                    'url' => $this->urlFor($request, 'menuBoard.delete.form', ['id' => $menuBoard->menuId]),
                    'text' => __('Delete')
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->menuBoardFactory->countLast();
        $this->getState()->setData($menuBoards);

        return $this->render($request, $response);
    }

    /**
     * Menu Board Add Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function addForm(Request $request, Response $response): Response
    {
        $this->getState()->template = 'menuboard-form-add';
        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/menuboard',
        operationId: 'menuBoardAdd',
        description: 'Add a new Menu Board',
        summary: 'Add Menu Board',
        tags: ['menuBoard']
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board name', type: 'string'),
                    new OA\Property(property: 'description', description: 'Menu Board description', type: 'string'),
                    new OA\Property(property: 'code', description: 'Menu Board code identifier', type: 'string'),
                    new OA\Property(property: 'folderId', description: 'Menu Board Folder Id', type: 'integer')
                ],
                required: ['name']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard'),
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ]
    )]
    /**
     * Add a new Menu Board
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $name = $sanitizedParams->getString('name');
        $description = $sanitizedParams->getString('description');
        $code = $sanitizedParams->getString('code');
        $folderId = $sanitizedParams->getInt('folderId');

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);

        $menuBoard = $this->menuBoardFactory->create($name, $description, $code);
        $menuBoard->folderId = $folder->getId();
        $menuBoard->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
        $menuBoard->save();

        // Return
        $this->getState()->hydrate([
            'message' => __('Added Menu Board'),
            'httpStatus' => 201,
            'id' => $menuBoard->menuId,
            'data' => $menuBoard,
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'menuboard-form-edit';
        $this->getState()->setData([
            'menuBoard' => $menuBoard
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/menuboard/{menuId}',
        operationId: 'menuBoardEdit',
        description: 'Edit existing Menu Board',
        summary: 'Edit Menu Board',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'The Menu Board ID to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board name', type: 'string'),
                    new OA\Property(property: 'description', description: 'Menu Board description', type: 'string'),
                    new OA\Property(property: 'code', description: 'Menu Board code identifier', type: 'string'),
                    new OA\Property(property: 'folderId', description: 'Menu Board Folder Id', type: 'integer')
                ],
                required: ['name']
            )
        ),
        required: true
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $menuBoard->name = $sanitizedParams->getString('name');
        $menuBoard->description = $sanitizedParams->getString('description');
        $menuBoard->code = $sanitizedParams->getString('code');
        $menuBoard->folderId = $sanitizedParams->getInt('folderId', ['default' => $menuBoard->folderId]);

        if ($menuBoard->hasPropertyChanged('folderId')) {
            if ($menuBoard->folderId === 1) {
                $this->checkRootFolderAllowSave();
            }
            $folder = $this->folderFactory->getById($menuBoard->folderId);
            $menuBoard->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();
        }

        $menuBoard->save();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $menuBoard->name),
            'id' => $menuBoard->menuId,
            'data' => $menuBoard
        ]);

        return $this->render($request, $response);
    }


    /**
     *
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'menuboard-form-delete';
        $this->getState()->setData([
            'menuBoard' => $menuBoard
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/menuboard/{menuId}',
        operationId: 'menuBoardDelete',
        description: 'Delete existing Menu Board',
        summary: 'Delete Menu Board',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'The Menu Board ID to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        // Issue the delete
        $menuBoard->delete();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $menuBoard->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Select Folder Form
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolderForm(Request $request, Response $response, $id)
    {
        // Get the Menu Board
        $menuBoard = $this->menuBoardFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $data = [
            'menuBoard' => $menuBoard
        ];

        $this->getState()->template = 'menuboard-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/menuboard/{id}/selectfolder',
        operationId: 'menuBoardSelectFolder',
        description: 'Select Folder for Menu Board',
        summary: 'Menu Board Select folder',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'The Menu Board ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
                        type: 'integer'
                    )
                ],
                required: ['folderId']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the Menu Board
        $menuBoard = $this->menuBoardFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $menuBoard->folderId = $folderId;
        $folder = $this->folderFactory->getById($menuBoard->folderId);
        $menuBoard->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();

        // Save
        $menuBoard->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Menu Board %s moved to Folder %s'), $menuBoard->name, $folder->text)
        ]);

        return $this->render($request, $response);
    }
}
