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
use Xibo\Entity\MenuBoardCategory as MenuBoardCategoryEntity;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class MenuBoardCategory extends Base
{
    public function __construct(
        private readonly MenuBoardFactory $menuBoardFactory,
        private readonly MenuBoardCategoryFactory $menuBoardCategoryFactory,
        private readonly MediaFactory $mediaFactory,
    ) {
    }

    #[OA\Get(
        path: '/menuboard/{menuId}/categories',
        operationId: 'menuBoardCategorySearch',
        description: 'Search all Menu Boards Categories this user has access to',
        summary: 'Search Menu Board Categories',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'Filter by Menu board Id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'Filter by Menu Board Category Id',
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
            enum: ['menuCategoryId', 'name', 'code']
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
    public function grid(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $menuBoard = $this->menuBoardFactory->getById($id, false);

        $menuBoardCategories = $this->menuBoardCategoryFactory->query(
            $this->gridRenderSort($parsedParams, $this->isJson($request)),
            $this->getMenuBoardCategoryFilters($parsedParams, $menuBoard->menuId)
        );

        foreach ($menuBoardCategories as $menuBoardCategory) {
            $this->decorateCategoryForGrid($request, $menuBoardCategory);
        }

        if ($this->isJson($request)) {
            return $response
                ->withStatus(200)
                ->withHeader('X-Total-Count', $this->menuBoardCategoryFactory->countLast())
                ->withJson($menuBoardCategories);
        }

        // TODO remove once Layout Designer is updated.
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->menuBoardCategoryFactory->countLast();
        $this->getState()->setData($menuBoardCategories);
        return $this->render($request, $response);
    }

    private function getMenuBoardCategoryFilters(SanitizerInterface $params, int $menuId): array
    {
        return $this->gridRenderFilter([
            'menuId'         => $menuId,
            'menuCategoryId' => $params->getInt('menuCategoryId'),
            'name'           => $params->getString('name'),
            'code'           => $params->getString('code'),
            'keyword'        => $params->getString('keyword'),
        ], $params);
    }

    private function decorateCategoryForGrid(Request $request, MenuBoardCategoryEntity $category): void
    {
        if ($category->mediaId == 0) {
            return;
        }

        $category->setUnmatchedProperty(
            'thumbnail',
            $this->urlFor($request, 'library.download', ['id' => $category->mediaId], ['preview' => 1])
        );

        try {
            $media = $this->mediaFactory->getById($category->mediaId);
            $category->setUnmatchedProperty('mediaType', $media->mediaType);
        } catch (\Exception $e) {
            $category->setUnmatchedProperty('mediaType', null);
        }
    }

    #[OA\Get(
        path: '/menuboard/category/{menuCategoryId}',
        operationId: 'menuBoardCategorySearchById',
        description: 'Get the Menu Board Category object specified by the provided menuCategoryId',
        summary: 'Search Menu Board Category by ID',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'Numeric ID of the Menu Board Category to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoardCategory')
    )]
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);
        $this->menuBoardFactory->getById($menuBoardCategory->menuId, false);
        $this->decorateCategoryForGrid($request, $menuBoardCategory);

        return $response
            ->withStatus(200)
            ->withJson($menuBoardCategory);
    }

    #[OA\Post(
        path: '/menuboard/category/copy/{menuCategoryId}',
        operationId: 'menuBoardCategoryCopy',
        description: 'Copy a Menu Board Category and all of its Products',
        summary: 'Copy Menu Board Category',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'The Menu Board Category ID to copy',
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
                    new OA\Property(property: 'name', description: 'Menu Board Category name', type: 'string'),
                    new OA\Property(
                        property: 'description',
                        description: 'Menu Board Category description',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'code',
                        description: 'Menu Board Category code identifier',
                        type: 'string'
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
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoardCategory')
    )]
    public function copy(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkViewable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $params = $this->getSanitizer($request->getParams());
        $newCategory = $menuBoardCategory->copyWithCascade(
            $menuBoardCategory->menuId,
            $params->getString('name'),
            $params->getString('description'),
            $params->getString('code')
        );

        return $response
            ->withStatus(201)
            ->withJson($newCategory);
    }

    #[OA\Post(
        path: '/menuboard/{menuId}/category',
        operationId: 'menuBoardCategoryAdd',
        description: 'Add a new Menu Board Category',
        summary: 'Add Menu Board Category',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'The Menu Board ID to which we want to add this Category to',
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
                    new OA\Property(property: 'name', description: 'Menu Board Category name', type: 'string'),
                    new OA\Property(
                        property: 'mediaId',
                        description: 'Media ID associated with this Menu Board Category',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'code',
                        description: 'Menu Board Category code identifier',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'description',
                        description: 'Menu Board Category description',
                        type: 'string'
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
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoard')
    )]
    public function add(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->create(
            $id,
            $sanitizedParams->getString('name'),
            $sanitizedParams->getInt('mediaId'),
            $sanitizedParams->getString('code'),
            $sanitizedParams->getString('description')
        );
        $menuBoardCategory->save();
        $menuBoard->save(['audit' => false]);

        return $response
            ->withStatus(201)
            ->withJson($menuBoardCategory);
    }

    #[OA\Put(
        path: '/menuboard/{menuCategoryId}/category',
        operationId: 'menuBoardCategoryEdit',
        description: 'Edit existing Menu Board Category',
        summary: 'Edit Menu Board Category',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'The Menu Board Category ID to Edit',
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
                    new OA\Property(
                        property: 'mediaId',
                        description: 'Media ID from CMS Library to associate with this Menu Board Category', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'code',
                        description: 'Menu Board Category code identifier',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'description',
                        description: 'Menu Board Category description',
                        type: 'string'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    public function edit(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);

        $menuBoardCategory->name = $sanitizedParams->getString('name');
        $menuBoardCategory->mediaId = $sanitizedParams->getInt('mediaId');
        $menuBoardCategory->code = $sanitizedParams->getString('code');
        $menuBoardCategory->description = $sanitizedParams->getString('description');
        $menuBoardCategory->save();
        $menuBoard->save();

        return $response
            ->withStatus(200)
            ->withJson($menuBoardCategory);
    }

    #[OA\Delete(
        path: '/menuboard/{menuCategoryId}/category',
        operationId: 'menuBoardCategoryDelete',
        description: 'Delete existing Menu Board Category',
        summary: 'Delete Menu Board Category',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'The Menu Board Category ID to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);
        $menuBoardCategory->delete();

        return $response->withStatus(204);
    }
}
