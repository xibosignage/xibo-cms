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

namespace Xibo\Widget;

use Carbon\Carbon;
use Xibo\Event\MenuBoardCategoryRequest;
use Xibo\Event\MenuBoardModifiedDtRequest;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Menu Board Category Provider
 */
class MenuBoardCategoryProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $this->getLog()->debug('fetchData: MenuBoardCategoryRequest passing to event');
        $this->getDispatcher()->dispatch(new MenuBoardCategoryRequest($dataProvider), MenuBoardCategoryRequest::$NAME);
        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        $this->getLog()->debug('fetchData: MenuBoardCategoryProvider passing to modifiedDt request event');

        $menuId = $dataProvider->getProperty('menuId');

        if ($menuId !== null) {
            // Raise an event to get the modifiedDt of this dataSet
            $event = new MenuBoardModifiedDtRequest($menuId);
            $this->getDispatcher()->dispatch($event, MenuBoardModifiedDtRequest::$NAME);

            return max($event->getModifiedDt(), $dataProvider->getWidgetModifiedDt());
        }

        return null;
    }
}
