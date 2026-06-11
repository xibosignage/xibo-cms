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
use Xibo\Entity\MenuBoardProduct as MenuBoardProductEntity;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Factory\MenuBoardProductOptionFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class MenuBoardProduct extends Base
{
    public function __construct(
        private readonly MenuBoardFactory $menuBoardFactory,
        private readonly MenuBoardCategoryFactory $menuBoardCategoryFactory,
        private readonly MenuBoardProductOptionFactory $menuBoardProductOptionFactory,
        private readonly MediaFactory $mediaFactory,
    ) {
    }

    #[OA\Get(
        path: '/menuboard/{menuCategoryId}/products',
        operationId: 'menuBoardProductsSearch',
        description: 'Search all Menu Boards Products this user has access to',
        summary: 'Search Menu Board Products',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'Filter by Menu Board Category Id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'menuId',
        description: 'Filter by Menu board Id',
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
            enum: ['menuProductId', 'name', 'price', 'displayOrder', 'availability']
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
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id, false);

        $menuBoardProducts = $this->menuBoardCategoryFactory->getProductData(
            $this->gridRenderSort($parsedParams, $this->isJson($request)),
            $this->getMenuBoardProductFilters($parsedParams, $id)
        );

        foreach ($menuBoardProducts as $menuBoardProduct) {
            $this->decorateProductForGrid($request, $menuBoardProduct);
        }

        $menuBoard->setActive();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->menuBoardCategoryFactory->countLast())
            ->withJson($menuBoardProducts);
    }

    private function getMenuBoardProductFilters(SanitizerInterface $params, int $categoryId): array
    {
        return $this->gridRenderFilter([
            'menuProductId'  => $params->getInt('menuProductId'),
            'menuCategoryId' => $categoryId,
            'name'           => $params->getString('name'),
            'code'           => $params->getString('code'),
            'keyword'        => $params->getString('keyword'),
            'availability'   => $params->getInt('availability'),
        ], $params);
    }

    private function decorateProductForGrid(Request $request, MenuBoardProductEntity $product): void
    {
        if ($product->mediaId != 0) {
            $product->setUnmatchedProperty(
                'thumbnail',
                $this->urlFor($request, 'library.download', ['id' => $product->mediaId], ['preview' => 1])
            );

            try {
                $media = $this->mediaFactory->getById($product->mediaId);
                $product->setUnmatchedProperty('mediaType', $media->mediaType);
            } catch (\Exception $e) {
                $product->setUnmatchedProperty('mediaType', null);
            }
        }

        $product->productOptions = $product->getOptions();
    }

    #[OA\Get(
        path: '/menuboard/product/{menuProductId}',
        operationId: 'menuBoardProductSearchById',
        description: 'Get the Menu Board Product object specified by the provided menuProductId',
        summary: 'Search Menu Board Product by ID',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuProductId',
        description: 'Numeric ID of the Menu Board Product to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/MenuBoardProduct')
    )]
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoardProduct = $this->menuBoardCategoryFactory->getByProductId($id);
        $this->menuBoardFactory->getById($menuBoardProduct->menuId, false);
        $this->decorateProductForGrid($request, $menuBoardProduct);

        return $response
            ->withStatus(200)
            ->withJson($menuBoardProduct);
    }

    public function productsForWidget(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());

        if ($parsedParams->getInt('menuId') !== null) {
            $this->menuBoardFactory->getById($parsedParams->getInt('menuId'), false);
        }

        $filter = $this->gridRenderFilter([
            'menuId'         => $parsedParams->getInt('menuId'),
            'menuProductId'  => $parsedParams->getInt('menuProductId'),
            'menuCategoryId' => $parsedParams->getInt('menuCategoryId'),
            'name'           => $parsedParams->getString('name'),
            'availability'   => $parsedParams->getInt('availability'),
            'categories'     => $parsedParams->getString('categories'),
        ], $parsedParams);

        $menuBoardProducts = $this->menuBoardCategoryFactory->getProductData(
            $this->gridRenderSort($parsedParams),
            $filter
        );

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->menuBoardCategoryFactory->countLast())
            ->withJson($menuBoardProducts);
    }

    #[OA\Post(
        path: '/menuboard/{menuCategoryId}/product',
        operationId: 'menuBoardProductAdd',
        description: 'Add a new Menu Board Product',
        summary: 'Add Menu Board Product',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuCategoryId',
        description: 'The Menu Board Category ID to which we want to add this Product to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name', 'displayOrder'],
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board Product name', type: 'string'),
                    new OA\Property(
                        property: 'description',
                        description: 'Menu Board Product description',
                        type: 'string'
                    ),
                    new OA\Property(property: 'price', description: 'Menu Board Product price', type: 'number'),
                    new OA\Property(
                        property: 'allergyInfo',
                        description: 'Menu Board Product allergyInfo',
                        type: 'string'
                    ),
                    new OA\Property(property: 'calories', description: 'Menu Board Product calories', type: 'integer'),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'Menu Board Product Display Order, used for sorting',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'availability',
                        description: 'Menu Board Product availability',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'mediaId',
                        description: 'Media ID from CMS Library to associate with this Menu Board Product', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(property: 'code', description: 'Menu Board Product code', type: 'string'),
                    new OA\Property(
                        property: 'productOptions',
                        description: 'An array of optional Product Option names',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'productValues',
                        description: 'An array of optional Product Option values',
                        type: 'array',
                        items: new OA\Items(type: 'string')
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
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $displayOrder = $sanitizedParams->getInt('displayOrder');
        if ($displayOrder === null) {
            $displayOrder = $this->menuBoardCategoryFactory->getNextDisplayOrder($menuBoardCategory->menuCategoryId);
        }

        $menuBoardProduct = $this->menuBoardCategoryFactory->createProduct(
            $menuBoard->menuId,
            $menuBoardCategory->menuCategoryId,
            $sanitizedParams->getString('name'),
            $sanitizedParams->getDouble('price'),
            $sanitizedParams->getString('description'),
            $sanitizedParams->getString('allergyInfo'),
            $sanitizedParams->getInt('calories'),
            $displayOrder,
            $sanitizedParams->getCheckbox('availability'),
            $sanitizedParams->getInt('mediaId') ?: null,
            $sanitizedParams->getString('code')
        );
        $menuBoardProduct->save();

        $productOptions = $sanitizedParams->getArray('productOptions', ['default' => []]);
        $productValues = $sanitizedParams->getArray('productValues', ['default' => []]);

        if (!empty(array_filter($productOptions)) && !empty(array_filter($productValues))) {
            $productDetails = array_filter(array_combine($productOptions, $productValues));
            $parsedDetails = $this->getSanitizer($productDetails);

            foreach ($productDetails as $option => $value) {
                $productOption = $this->menuBoardProductOptionFactory->create(
                    $menuBoardProduct->menuProductId,
                    $option,
                    $parsedDetails->getDouble($option)
                );
                $productOption->save();
            }
        }
        $menuBoardProduct->productOptions = $menuBoardProduct->getOptions();
        $menuBoard->save();

        return $response
            ->withStatus(201)
            ->withJson($menuBoardProduct);
    }

    #[OA\Put(
        path: '/menuboard/{menuProductId}/product',
        operationId: 'menuBoardProductEdit',
        description: 'Edit existing Menu Board Product',
        summary: 'Edit Menu Board Product',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuProductId',
        description: 'The Menu Board Product ID to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name', 'displayOrder'],
                properties: [
                    new OA\Property(property: 'name', description: 'Menu Board Product name', type: 'string'),
                    new OA\Property(
                        property: 'description',
                        description: 'Menu Board Product description',
                        type: 'string'
                    ),
                    new OA\Property(property: 'price', description: 'Menu Board Product price', type: 'number'),
                    new OA\Property(
                        property: 'allergyInfo',
                        description: 'Menu Board Product allergyInfo',
                        type: 'string'
                    ),
                    new OA\Property(property: 'calories', description: 'Menu Board Product calories', type: 'integer'),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'Menu Board Product Display Order, used for sorting',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'availability',
                        description: 'Menu Board Product availability',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'mediaId',
                        description: 'Media ID from CMS Library to associate with this Menu Board Product', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(property: 'code', description: 'Menu Board Product code', type: 'string'),
                    new OA\Property(
                        property: 'productOptions',
                        description: 'An array of optional Product Option names',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'productValues',
                        description: 'An array of optional Product Option values',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    public function edit(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoardProduct = $this->menuBoardCategoryFactory->getByProductId($id);
        $menuBoard = $this->menuBoardFactory->getById($menuBoardProduct->menuId);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $menuBoardProduct->name = $sanitizedParams->getString('name');
        $menuBoardProduct->description = $sanitizedParams->getString('description');
        $menuBoardProduct->price = $sanitizedParams->getDouble('price');
        $menuBoardProduct->allergyInfo = $sanitizedParams->getString('allergyInfo');
        $menuBoardProduct->calories = $sanitizedParams->getInt('calories');
        $menuBoardProduct->displayOrder = $sanitizedParams->getInt('displayOrder');
        $menuBoardProduct->availability = $sanitizedParams->getCheckbox('availability');
        $menuBoardProduct->mediaId = $sanitizedParams->getInt('mediaId') ?: null;
        $menuBoardProduct->code = $sanitizedParams->getString('code');
        $productOptions = $sanitizedParams->getArray('productOptions', ['default' => []]);
        $productValues = $sanitizedParams->getArray('productValues', ['default' => []]);

        if (!empty(array_filter($productOptions)) && !empty(array_filter($productValues))) {
            $productDetails = array_filter(array_combine($productOptions, $productValues));
            $parsedDetails = $this->getSanitizer($productDetails);
            if (count($menuBoardProduct->getOptions()) > count($productDetails)) {
                $menuBoardProduct->removeOptions();
            }

            foreach ($productDetails as $option => $value) {
                $productOption = $this->menuBoardProductOptionFactory->create(
                    $menuBoardProduct->menuProductId,
                    $option,
                    $parsedDetails->getDouble($option)
                );
                $productOption->save();
            }
        } else {
            $menuBoardProduct->removeOptions();
        }
        $menuBoardProduct->productOptions = $menuBoardProduct->getOptions();
        $menuBoardProduct->save();
        $menuBoard->save();

        return $response
            ->withStatus(200)
            ->withJson($menuBoardProduct);
    }

    #[OA\Delete(
        path: '/menuboard/{menuProductId}/product',
        operationId: 'menuBoardProductDelete',
        description: 'Delete existing Menu Board Product',
        summary: 'Delete Menu Board Product',
        tags: ['menuBoard']
    )]
    #[OA\Parameter(
        name: 'menuProductId',
        description: 'The Menu Board Product ID to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $menuBoardProduct = $this->menuBoardCategoryFactory->getByProductId($id);
        $menuBoard = $this->menuBoardFactory->getById($menuBoardProduct->menuId);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardProduct->delete();

        return $response->withStatus(204);
    }
}
