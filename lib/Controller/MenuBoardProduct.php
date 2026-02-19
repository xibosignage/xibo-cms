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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Factory\MenuBoardProductOptionFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class MenuBoardProduct extends Base
{
    /**
     * @var MenuBoardFactory
     */
    private $menuBoardFactory;

    /**
     * @var MenuBoardCategoryFactory
     */
    private $menuBoardCategoryFactory;

    /**
     * @var MenuBoardProductOptionFactory
     */
    private $menuBoardProductOptionFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Set common dependencies.
     * @param MenuBoardFactory $menuBoardFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param MenuBoardProductOptionFactory $menuBoardProductOptionFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct(
        $menuBoardFactory,
        $menuBoardCategoryFactory,
        $menuBoardProductOptionFactory,
        $mediaFactory
    ) {
        $this->menuBoardFactory = $menuBoardFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->menuBoardProductOptionFactory = $menuBoardProductOptionFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Displays the Menu Board Page
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response, $id)
    {
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);
        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);
        $categories = $this->menuBoardCategoryFactory->getByMenuId($menuBoard->menuId);

        // Call to render the template
        $this->getState()->template = 'menuboard-product-page';
        $this->getState()->setData([
            'menuBoard' => $menuBoard,
            'menuBoardCategory' => $menuBoardCategory,
            'categories' => $categories
        ]);

        return $this->render($request, $response);
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
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/MenuBoard')
        )
    )]
    /**
     * Returns a Grid of Menu Board Products
     *
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response, $id): Response
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        $filter = [
            'menuProductId' => $parsedParams->getInt('menuProductId'),
            'menuCategoryId' => $id,
            'name' => $parsedParams->getString('name'),
            'code' => $parsedParams->getString('code')
        ];

        $menuBoardProducts = $this->menuBoardCategoryFactory->getProductData(
            $this->gridRenderSort($parsedParams),
            $this->gridRenderFilter($filter, $parsedParams)
        );

        foreach ($menuBoardProducts as $menuBoardProduct) {
            if ($this->isApi($request)) {
                continue;
            }

            $menuBoardProduct->includeProperty('buttons');
            $menuBoardProduct->buttons = [];

            if ($menuBoardProduct->mediaId != 0) {
                $menuBoardProduct->setUnmatchedProperty(
                    'thumbnail',
                    $this->urlFor($request, 'library.download', ['id' => $menuBoardProduct->mediaId], ['preview' => 1]),
                );
            }

            if ($this->getUser()->featureEnabled('menuBoard.modify') && $this->getUser()->checkEditable($menuBoard)) {
                $menuBoardProduct->buttons[] = [
                    'id' => 'menuBoardProduct_edit_button',
                    'url' => $this->urlFor($request, 'menuBoard.product.edit.form', ['id' => $menuBoardProduct->menuProductId]),
                    'text' => __('Edit')
                ];
            }

            if ($this->getUser()->featureEnabled('menuBoard.modify') && $this->getUser()->checkDeleteable($menuBoard)) {
                $menuBoardProduct->buttons[] = ['divider' => true];

                $menuBoardProduct->buttons[] = [
                    'id' => 'menuBoardProduct_delete_button',
                    'url' => $this->urlFor($request, 'menuBoard.product.delete.form', ['id' => $menuBoardProduct->menuProductId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request, 'menuBoard.product.delete', ['id' => $menuBoardProduct->menuProductId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'menuBoardProduct_delete_button'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $menuBoardProduct->name]
                    ]
                ];
            }
        }

        $menuBoard->setActive();

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->menuBoardCategoryFactory->countLast();
        $this->getState()->setData($menuBoardProducts);

        return $this->render($request, $response);
    }

    public function productsForWidget(Request $request, Response $response): Response
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $categories = $parsedParams->getString('categories');

        $filter = [
            'menuId' => $parsedParams->getInt('menuId'),
            'menuProductId' => $parsedParams->getInt('menuProductId'),
            'menuCategoryId' => $parsedParams->getInt('menuCategoryId'),
            'name' => $parsedParams->getString('name'),
            'availability' => $parsedParams->getInt('availability'),
            'categories' => $categories
        ];

        $menuBoardProducts = $this->menuBoardCategoryFactory->getProductData(
            $this->gridRenderSort($parsedParams),
            $this->gridRenderFilter($filter, $parsedParams)
        );

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->menuBoardCategoryFactory->countLast();
        $this->getState()->setData($menuBoardProducts);

        return $this->render($request, $response);
    }

    /**
     * Menu Board Category Add Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function addForm(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);

        $this->getState()->template = 'menuboard-product-form-add';
        $this->getState()->setData([
            'menuBoard' => $menuBoard,
            'menuBoardCategory' => $menuBoardCategory
        ]);

        return $this->render($request, $response);
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
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
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
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    ),
                    new OA\Property(
                        property: 'productValues',
                        description: 'An array of optional Product Option values',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    )
                ],
                required: ['name', 'displayOrder']
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
     * Add a new Menu Board Product
     *
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function add(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $name = $sanitizedParams->getString('name');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $price = $sanitizedParams->getDouble('price');
        $description = $sanitizedParams->getString('description');
        $allergyInfo = $sanitizedParams->getString('allergyInfo');
        $calories = $sanitizedParams->getInt('calories');
        $displayOrder = $sanitizedParams->getInt('displayOrder');
        $availability = $sanitizedParams->getCheckbox('availability');
        $productOptions = $sanitizedParams->getArray('productOptions', ['default' => []]);
        $productValues = $sanitizedParams->getArray('productValues', ['default' => []]);
        $code = $sanitizedParams->getString('code');

        // If the display order is empty, get the next highest one.
        if ($displayOrder === null) {
            $displayOrder = $this->menuBoardCategoryFactory->getNextDisplayOrder($menuBoardCategory->menuCategoryId);
        }

        $menuBoardProduct = $this->menuBoardCategoryFactory->createProduct(
            $menuBoard->menuId,
            $menuBoardCategory->menuCategoryId,
            $name,
            $price,
            $description,
            $allergyInfo,
            $calories,
            $displayOrder,
            $availability,
            $mediaId,
            $code
        );
        $menuBoardProduct->save();

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

        // Return
        $this->getState()->hydrate([
            'message' => __('Added Menu Board Product'),
            'httpStatus' => 201,
            'id' => $menuBoardProduct->menuProductId,
            'data' => $menuBoardProduct
        ]);

        return $this->render($request, $response);
    }

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
    public function editForm(Request $request, Response $response, $id): Response
    {
        $menuBoardProduct = $this->menuBoardCategoryFactory->getByProductId($id);
        $menuBoard = $this->menuBoardFactory->getById($menuBoardProduct->menuId);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'menuboard-product-form-edit';
        $this->getState()->setData([
            'menuBoardProduct' => $menuBoardProduct,
            'media' => $menuBoardProduct->mediaId != null ? $this->mediaFactory->getById($menuBoardProduct->mediaId) : null
        ]);

        return $this->render($request, $response);
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
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
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
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    ),
                    new OA\Property(
                        property: 'productValues',
                        description: 'An array of optional Product Option values',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    )
                ],
                required: ['name', 'displayOrder']
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
        $menuBoardProduct->mediaId = $sanitizedParams->getInt('mediaId');
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

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $menuBoardProduct->name),
            'id' => $menuBoardProduct->menuProductId,
            'data' => $menuBoardProduct
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
        $menuBoardProduct = $this->menuBoardCategoryFactory->getByProductId($id);
        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($menuBoardProduct->menuCategoryId);
        $menuBoard = $this->menuBoardFactory->getById($menuBoardProduct->menuId);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'menuboard-product-form-delete';
        $this->getState()->setData([
            'menuBoard' => $menuBoard,
            'menuBoardCategory' => $menuBoardCategory,
            'menuBoardProduct' => $menuBoardProduct
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/menuboard/{menuProductId}/product',
        operationId: 'menuBoardProductDelete',
        description: 'Delete existing Menu Board Product',
        summary: 'Delete Menu Board',
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
        $menuBoardProduct = $this->menuBoardCategoryFactory->getByProductId($id);
        $menuBoard = $this->menuBoardFactory->getById($menuBoardProduct->menuId);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        // Issue the delete
        $menuBoardProduct->delete();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $menuBoardProduct->name)
        ]);

        return $this->render($request, $response);
    }
}
