<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * A widget provider for stocks and currencies, only used to correctly set the numItems
 */
class CurrenciesAndStocksProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    /**
     * We want to pass this out to the event mechanism for 3rd party sources.
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return \Xibo\Widget\Provider\WidgetProviderInterface
     */
    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $dataProvider->setIsUseEvent();
        return $this;
    }

    /**
     * Special handling for currencies and stocks where the number of data items is based on the quantity of
     * items input in the `items` property.
     * @param \Xibo\Widget\Provider\DurationProviderInterface $durationProvider
     * @return \Xibo\Widget\Provider\WidgetProviderInterface
     */
    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        $this->getLog()->debug('fetchDuration: CurrenciesAndStocksProvider');

        // Currencies and stocks are based on the number of items set in the respective fields.
        $items = $durationProvider->getWidget()->getOptionValue('items', null);
        if ($items === null) {
            $this->getLog()->debug('fetchDuration: CurrenciesAndStocksProvider: no items set');
            return $this;
        }

        if ($durationProvider->getWidget()->getOptionValue('durationIsPerItem', 0) == 0) {
            $this->getLog()->debug('fetchDuration: CurrenciesAndStocksProvider: duration per item not set');
            return $this;
        }

        $numItems = count(explode(',', $items));

        $this->getLog()->debug('fetchDuration: CurrenciesAndStocksProvider: number of items: ' . $numItems);

        if ($numItems > 1) {
            // If we have paging involved then work out the page count.
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
        return null;
    }
}
