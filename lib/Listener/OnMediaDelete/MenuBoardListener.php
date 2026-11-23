<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Listener\OnMediaDelete;

use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class MenuBoardListener
{
    use ListenerLoggerTrait;

    /** @var MenuBoardCategoryFactory */
    private $menuBoardCategoryFactory;

    /** @var MenuBoardFactory */
    private $menuBoardFactory;

    public function __construct($menuBoardCategoryFactory, $menuBoardFactory)
    {
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->menuBoardFactory = $menuBoardFactory;
    }

    /**
     * @param MediaDeleteEvent $event
     * @throws InvalidArgumentException
     */
    public function __invoke(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();
        $affectedMenuIds = [];

        foreach ($this->menuBoardCategoryFactory->query(null, ['mediaId' => $media->mediaId]) as $category) {
            $category->mediaId = null;
            $category->save();
            $affectedMenuIds[$category->menuId] = true;
        }

        foreach ($this->menuBoardCategoryFactory->getProductData(null, ['mediaId' => $media->mediaId]) as $product) {
            $product->mediaId = null;
            $product->save();
            $affectedMenuIds[$product->menuId] = true;
        }

        foreach (array_keys($affectedMenuIds) as $menuId) {
            try {
                $this->menuBoardFactory->getById($menuId)->touch();
            } catch (NotFoundException) {
                $this->getLogger()->error('MenuBoardListener: menu board ' . $menuId . ' not found while touching');
            }
        }
    }
}
