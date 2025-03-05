<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class MenuBoardCategory extends Base
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
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Set common dependencies.
     * @param MenuBoardFactory $menuBoardFactory
     * @param $menuBoardCategoryFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct(
        $menuBoardFactory,
        $menuBoardCategoryFactory,
        $mediaFactory
    ) {
        $this->menuBoardFactory = $menuBoardFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Displays the Menu Board Categories Page
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response, $id)
    {
        $menuBoard = $this->menuBoardFactory->getById($id);

        // Call to render the template
        $this->getState()->template = 'menuboard-category-page';
        $this->getState()->setData([
            'menuBoard' => $menuBoard
        ]);

        return $this->render($request, $response);
    }

    /**
     * Returns a Grid of Menu Board Categories
     *
     * @SWG\Get(
     *  path="/menuboard/{menuId}/categories",
     *  operationId="menuBoardCategorySearch",
     *  tags={"menuBoard"},
     *  summary="Search Menu Board Categories",
     *  description="Search all Menu Boards Categories this user has access to",
     *  @SWG\Parameter(
     *      name="menuId",
     *      in="path",
     *      description="Filter by Menu board Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="menuCategoryId",
     *      in="query",
     *      description="Filter by Menu Board Category Id",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="name",
     *      in="query",
     *      description="Filter by name",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="code",
     *      in="query",
     *      description="Filter by code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/MenuBoard")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response, $id): Response
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $menuBoard = $this->menuBoardFactory->getById($id);

        $filter = [
            'menuId' => $menuBoard->menuId,
            'menuCategoryId' => $parsedParams->getInt('menuCategoryId'),
            'name' => $parsedParams->getString('name'),
            'code' => $parsedParams->getString('code')
        ];

        $menuBoardCategories = $this->menuBoardCategoryFactory->query(
            $this->gridRenderSort($parsedParams),
            $this->gridRenderFilter($filter, $parsedParams)
        );


        foreach ($menuBoardCategories as $menuBoardCategory) {
            if ($this->isApi($request)) {
                continue;
            }

            if ($menuBoardCategory->mediaId != 0) {
                $menuBoardCategory->setUnmatchedProperty(
                    'thumbnail',
                    $this->urlFor(
                        $request,
                        'library.download',
                        ['id' => $menuBoardCategory->mediaId],
                        ['preview' => 1],
                    )
                );
            }

            $menuBoardCategory->includeProperty('buttons');
            $menuBoardCategory->buttons = [];

            if ($this->getUser()->featureEnabled('menuBoard.modify') && $this->getUser()->checkEditable($menuBoard)) {
                $menuBoardCategory->buttons[] = [
                    'id' => 'menuBoardCategory_button_viewproducts',
                    'url' => $this->urlFor($request, 'menuBoard.product.view', ['id' => $menuBoardCategory->menuCategoryId]),
                    'class' => 'XiboRedirectButton',
                    'text' => __('View Products')
                ];

                $menuBoardCategory->buttons[] = [
                    'id' => 'menuBoardCategory_edit_button',
                    'url' => $this->urlFor($request, 'menuBoard.category.edit.form', ['id' => $menuBoardCategory->menuCategoryId]),
                    'text' => __('Edit')
                ];
            }

            if ($this->getUser()->featureEnabled('menuBoard.modify') && $this->getUser()->checkDeleteable($menuBoard)) {
                $menuBoardCategory->buttons[] = ['divider' => true];

                $menuBoardCategory->buttons[] = [
                    'id' => 'menuBoardCategory_delete_button',
                    'url' => $this->urlFor($request, 'menuBoard.category.delete.form', ['id' => $menuBoardCategory->menuCategoryId]),
                    'text' => __('Delete')
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->menuBoardCategoryFactory->countLast();
        $this->getState()->setData($menuBoardCategories);

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
        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'menuboard-category-form-add';
        $this->getState()->setData([
            'menuBoard' => $menuBoard
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a new Menu Board Category
     *
     * @SWG\Post(
     *  path="/menuboard/{menuId}/category",
     *  operationId="menuBoardCategoryAdd",
     *  tags={"menuBoard"},
     *  summary="Add Menu Board",
     *  description="Add a new Menu Board Category",
     *  @SWG\Parameter(
     *      name="menuId",
     *      in="path",
     *      description="The Menu Board ID to which we want to add this Category to",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Menu Board Category name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="formData",
     *      description="Media ID associated with this Menu Board Category",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Menu Board Category code identifier",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="Menu Board Category description",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/MenuBoard"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function add(Request $request, Response $response, $id): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $menuBoard = $this->menuBoardFactory->getById($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $name = $sanitizedParams->getString('name');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $code = $sanitizedParams->getString('code');
        $description = $sanitizedParams->getString('description');

        $menuBoardCategory = $this->menuBoardCategoryFactory->create($id, $name, $mediaId, $code, $description);
        $menuBoardCategory->save();
        $menuBoard->save(['audit' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Added Menu Board Category'),
            'httpStatus' => 201,
            'id' => $menuBoardCategory->menuCategoryId,
            'data' => $menuBoardCategory,
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
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkEditable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);

        $this->getState()->template = 'menuboard-category-form-edit';
        $this->getState()->setData([
            'menuBoardCategory' => $menuBoardCategory,
            'media' => $menuBoardCategory->mediaId != null ? $this->mediaFactory->getById($menuBoardCategory->mediaId) : null
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/menuboard/{menuCategoryId}/category",
     *  operationId="menuBoardCategoryEdit",
     *  tags={"menuBoard"},
     *  summary="Edit Menu Board Category",
     *  description="Edit existing Menu Board Category",
     *  @SWG\Parameter(
     *      name="menuCategoryId",
     *      in="path",
     *      description="The Menu Board Category ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Menu Board name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="formData",
     *      description="Media ID from CMS Library to associate with this Menu Board Category",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Menu Board Category code identifier",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="Menu Board Category description",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
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

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $menuBoardCategory->name),
            'id' => $menuBoardCategory->menuCategoryId,
            'data' => $menuBoardCategory
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
     */
    public function deleteForm(Request $request, Response $response, $id): Response
    {
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);

        $this->getState()->template = 'menuboard-category-form-delete';
        $this->getState()->setData([
            'menuBoardCategory' => $menuBoardCategory
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Delete(
     *  path="/menuboard/{menuCategoryId}/category",
     *  operationId="menuBoardCategoryDelete",
     *  tags={"menuBoard"},
     *  summary="Delete Menu Board Category",
     *  description="Delete existing Menu Board Category",
     *  @SWG\Parameter(
     *      name="menuId",
     *      in="path",
     *      description="The menuId to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
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
        $menuBoard = $this->menuBoardFactory->getByMenuCategoryId($id);

        if (!$this->getUser()->checkDeleteable($menuBoard)) {
            throw new AccessDeniedException();
        }

        $menuBoardCategory = $this->menuBoardCategoryFactory->getById($id);

        // Issue the delete
        $menuBoardCategory->delete();

        // Success
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $menuBoardCategory->name)
        ]);

        return $this->render($request, $response);
    }
}
