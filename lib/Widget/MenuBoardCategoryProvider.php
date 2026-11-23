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
