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
use Xibo\Factory\MenuBoardFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Menu Board Controller
 */
class MenuBoard extends Base
{
    public function __construct(
        private readonly MenuBoardFactory $menuBoardFactory,
        private readonly FolderFactory $folderFactory
    ) {
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
    #[OA\Parameter(
        name: 'modifiedDateFrom',
        description: 'Filter by modified date (from)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'modifiedDateTo',
        description: 'Filter by modified date (to)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by keyword (searches name)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: ['menuId', 'name', 'code', 'modifiedDt', 'owner', 'folderName']
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
            items: new OA\Items(ref: '#/components/schemas/MenuBoard')
        )
    )]
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());

        $menuBoards = $this->menuBoardFactory->query(
            $this->gridRenderSort($parsedParams, $this->isJson($request)),
            $this->getMenuBoardFilters($parsedParams)
        );

        foreach ($menuBoards as $menuBoard) {
            $menuBoard->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($menuBoard));
        }

        if ($this->isJson($request)) {
            return $response
                ->withStatus(200)
                ->withHeader('X-Total-Count', $this->menuBoardFactory->countLast())
                ->withJson($menuBoards);
        }

        // TODO remove once Layout Designer is updated.
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->menuBoardFactory->countLast();
        $this->getState()->setData($menuBoards);
        return $this->render($request, $response);
    }

    private function getMenuBoardFilters(SanitizerInterface $params): array
    {
        return $this->gridRenderFilter([
            'menuId'              => $params->getInt('menuId'),
            'userId'              => $params->getInt('userId'),
            'name'                => $params->getString('name'),
            'code'                => $params->getString('code'),
            'folderId'            => $params->getInt('folderId'),
            'logicalOperatorName' => $params->getString('logicalOperatorName'),
            'modifiedDateFrom'    => $params->getDate('modifiedDateFrom'),
            'modifiedDateTo'      => $params->getDate('modifiedDateTo'),
            'keyword'             => $params->getString('keyword'),
        ], $params);
    }

    #[OA\Get(
        path: '/menuboard/{menuId}',
        operationId: 'menuBoardSearchById',
        description: 'Get the Menu Board object specified by the provided menuId',
        summary: 'Search Menu Board by ID',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'Numeric ID of the Menu Board to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard')
    )]
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoard = $this->menuBoardFactory->getById($id, false);
        $menuBoard->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($menuBoard));

        return $response
            ->withStatus(200)
            ->withJson($menuBoard);
    }

    #[OA\Post(
        path: '/menuboard/copy/{menuId}',
        operationId: 'menuBoardCopy',
        description: 'Copy a Menu Board and all of its Categories and Products',
        summary: 'Copy Menu Board',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'The Menu Board ID to copy',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board name', type: 'string'),
                    new OA\Property(property: 'description', description: 'Menu Board description', type: 'string'),
                    new OA\Property(property: 'code', description: 'Menu Board code identifier', type: 'string')
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
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard')
    )]
    public function copy(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkViewable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $params = $this->getSanitizer($request->getParams());
        $newMenuBoard = $menuBoard->copyWithCascade(
            $params->getString('name'),
            $params->getString('description'),
            $params->getString('code'),
            $this->getUser()->userId
        );

        return $response
            ->withStatus(201)
            ->withJson($newMenuBoard);
    }

    #[OA\Post(
        path: '/menuboard',
        operationId: 'menuBoardAdd',
        description: 'Add a new Menu Board',
        summary: 'Add Menu Board',
        tags: ['menuBoard']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board name', type: 'string'),
                    new OA\Property(property: 'description', description: 'Menu Board description', type: 'string'),
                    new OA\Property(property: 'code', description: 'Menu Board code identifier', type: 'string'),
                    new OA\Property(property: 'folderId', description: 'Menu Board Folder Id', type: 'integer')
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
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard')
    )]
    public function add(Request $request, Response $response): Response|ResponseInterface
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

        return $response
            ->withStatus(201)
            ->withJson($menuBoard);
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
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board name', type: 'string'),
                    new OA\Property(property: 'description', description: 'Menu Board description', type: 'string'),
                    new OA\Property(property: 'code', description: 'Menu Board code identifier', type: 'string'),
                    new OA\Property(property: 'folderId', description: 'Menu Board Folder Id', type: 'integer')
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    public function edit(Request $request, Response $response, int $id): Response|ResponseInterface
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
            $menuBoard->permissionsFolderId = ($folder->getPermissionFolderId() == null)
                ? $folder->id
                : $folder->getPermissionFolderId();
        }

        $menuBoard->save();

        return $response
            ->withStatus(200)
            ->withJson($menuBoard);
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
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoard->delete();

        return $response->withStatus(204);
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
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['folderId'],
                properties: [
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
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard')
    )]
    public function selectFolder(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $menuBoard->folderId = $folderId;
        $folder = $this->folderFactory->getById($menuBoard->folderId);
        $menuBoard->permissionsFolderId = ($folder->getPermissionFolderId() == null)
            ? $folder->id
            : $folder->getPermissionFolderId();

        $menuBoard->save();

        return $response->withStatus(204);
    }
}
