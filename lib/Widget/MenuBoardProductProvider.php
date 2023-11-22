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
use Xibo\Event\MenuBoardModifiedDtRequest;
use Xibo\Event\MenuBoardProductRequest;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Menu Board Product Provider
 */
class MenuBoardProductProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $this->getLog()->debug('fetchData: MenuBoardProductProvider passing to event');
        $this->getDispatcher()->dispatch(new MenuBoardProductRequest($dataProvider), MenuBoardProductRequest::$NAME);
        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        if ($durationProvider->getWidget()->getOptionValue('durationIsPerItem', 0) == 1) {
            $this->getLog()->debug('fetchDuration: duration is per item');

            $lowerLimit = $durationProvider->getWidget()->getOptionValue('lowerLimit', 0);
            $upperLimit = $durationProvider->getWidget()->getOptionValue('upperLimit', 15);
            $numItems = $upperLimit - $lowerLimit;

            $itemsPerPage = $durationProvider->getWidget()->getOptionValue('itemsPerPage', 0);
            if ($itemsPerPage > 0) {
                $numItems = ceil($numItems / $itemsPerPage);
            }

            $durationProvider->setDuration($durationProvider->getWidget()->calculatedDuration * $numItems);
        }
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        $this->getLog()->debug('fetchData: MenuBoardProductProvider passing to modifiedDt request event');
        $menuId = $dataProvider->getProperty('menuId');
        if ($menuId !== null) {
            // Raise an event to get the modifiedDt of this dataSet
            $event = new MenuBoardModifiedDtRequest($menuId);
            $this->getDispatcher()->dispatch($event, MenuBoardModifiedDtRequest::$NAME);
            return max($event->getModifiedDt(), $dataProvider->getWidgetModifiedDt());
        } else {
            return null;
        }
    }
}
