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

namespace Xibo\Listener;

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\MenuBoardCategoryRequest;
use Xibo\Event\MenuBoardModifiedDtRequest;
use Xibo\Event\MenuBoardProductRequest;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Support\Exception\NotFoundException;

/**
 * Listener for dealing with a menu board provider
 */
class MenuBoardProviderListener
{
    use ListenerLoggerTrait;

    private MenuBoardFactory $menuBoardFactory;

    private MenuBoardCategoryFactory $menuBoardCategoryFactory;

    public function __construct(MenuBoardFactory $menuBoardFactory, MenuBoardCategoryFactory $menuBoardCategoryFactory)
    {
        $this->menuBoardFactory = $menuBoardFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): MenuBoardProviderListener
    {
        $dispatcher->addListener(MenuBoardProductRequest::$NAME, [$this, 'onProductRequest']);
        $dispatcher->addListener(MenuBoardCategoryRequest::$NAME, [$this, 'onCategoryRequest']);
        $dispatcher->addListener(MenuBoardModifiedDtRequest::$NAME, [$this, 'onModifiedDtRequest']);
        return $this;
    }

    public function onProductRequest(MenuBoardProductRequest $event): void
    {
        $this->getLogger()->debug('onProductRequest: data source is ' . $event->getDataProvider()->getDataSource());

        $dataProvider = $event->getDataProvider();
        $menuId = $dataProvider->getProperty('menuId', 0);
        if (empty($menuId)) {
            $this->getLogger()->debug('onProductRequest: no menuId.');
            return;
        }

        // Sorting
        $desc = $dataProvider->getProperty('sortDescending') == 1 ? ' DESC' : '';
        $sort = match ($dataProvider->getProperty('sortField')) {
            'name' => '`name`' . $desc,
            'price' => '`price`' . $desc,
            'id' => '`menuProductId`' . $desc,
            default => '`displayOrder`' . $desc,
        };

        // Build a filter
        $filter = [
            'menuId' => $menuId,
        ];

        $categoryId = $dataProvider->getProperty('categoryId');
        $this->getLogger()->debug('onProductRequest: $categoryId: ' . $categoryId);
        if ($categoryId !== null && $categoryId !== '') {
            $filter['menuCategoryId'] = intval($categoryId);
        }

        // Show Unavailable?
        if ($dataProvider->getProperty('showUnavailable', 0) === 0) {
            $filter['availability'] = 1;
        }

        // limits?
        $lowerLimit = $dataProvider->getProperty('lowerLimit', 0);
        $upperLimit = $dataProvider->getProperty('upperLimit', 0);
        if ($lowerLimit !== 0 || $upperLimit !== 0) {
            // Start should be the lower limit
            // Size should be the distance between upper and lower
            $filter['start'] = $lowerLimit;
            $filter['length'] = $upperLimit - $lowerLimit;

            $this->getLogger()->debug('onProductRequest: applied limits, start: '
                . $filter['start'] . ', length: ' . $filter['length']);
        }

        $products = $this->menuBoardCategoryFactory->getProductData([$sort], $filter);

        foreach ($products as $menuBoardProduct) {
            $menuBoardProduct->productOptions = $menuBoardProduct->getOptions();
            $product = $menuBoardProduct->toProduct();

            // Convert the image to a library image?
            if ($product->image !== null) {
                // The content is the ID of the image
                try {
                    $product->image = $dataProvider->addLibraryFile(intval($product->image));
                } catch (NotFoundException $notFoundException) {
                    $this->getLogger()->error('onProductRequest: Invalid library media reference: ' . $product->image);
                    $product->image = null;
                }
            }
            $dataProvider->addItem($product);
        }

        $dataProvider->setIsHandled();
    }

    public function onCategoryRequest(MenuBoardCategoryRequest $event): void
    {
        $this->getLogger()->debug('onCategoryRequest: data source is ' . $event->getDataProvider()->getDataSource());

        $dataProvider = $event->getDataProvider();
        $menuId = $dataProvider->getProperty('menuId', 0);
        if (empty($menuId)) {
            $this->getLogger()->debug('onCategoryRequest: no menuId.');
            return;
        }
        $categoryId = $dataProvider->getProperty('categoryId', 0);
        if (empty($categoryId)) {
            $this->getLogger()->debug('onCategoryRequest: no categoryId.');
            return;
        }

        $category = $this->menuBoardCategoryFactory->getById($categoryId)->toProductCategory();
        // Convert the image to a library image?
        if ($category->image !== null) {
            // The content is the ID of the image
            try {
                $category->image = $dataProvider->addLibraryFile(intval($category->image));
            } catch (NotFoundException $notFoundException) {
                $this->getLogger()->error('onCategoryRequest: Invalid library media reference: ' . $category->image);
                $category->image = null;
            }
        }
        $dataProvider->addItem($category);

        $dataProvider->setIsHandled();
    }

    public function onModifiedDtRequest(MenuBoardModifiedDtRequest $event): void
    {
        $menu = $this->menuBoardFactory->getById($event->getDataSetId());
        $event->setModifiedDt(Carbon::createFromTimestamp($menu->modifiedDt));
    }
}
