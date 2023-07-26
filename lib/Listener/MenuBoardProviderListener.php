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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\MenuBoardProductRequest;
use Xibo\Factory\MenuBoardCategoryFactory;

/**
 * Listener for dealing with a menu board provider
 */
class MenuBoardProviderListener
{
    use ListenerLoggerTrait;

    private MenuBoardCategoryFactory $menuBoardCategoryFactory;

    public function __construct(MenuBoardCategoryFactory $menuBoardCategoryFactory)
    {
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): MenuBoardProviderListener
    {
        $dispatcher->addListener(MenuBoardProductRequest::$NAME, [$this, 'onProductRequest']);
        return $this;
    }

    public function onProductRequest(MenuBoardProductRequest $event): void
    {
        $this->getLogger()->debug('onProductRequest: data source is ' . $event->getDataProvider()->getDataSource());

        $dataProvider = $event->getDataProvider();
        $menuId = $dataProvider->getProperty('menuId', 0);
        if (empty($menuId)) {
            $this->getLogger()->debug('onDataRequest: no menuId.');
            return;
        }

        $products = $this->menuBoardCategoryFactory->getProductData(null, ['menuId' => $menuId]);

        foreach ($products as $product) {
            $dataProvider->addItem($product->toProduct());
        }

        $dataProvider->setIsHandled();
    }
}
