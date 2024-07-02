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
use Xibo\Event\DataSetDataRequestEvent;
use Xibo\Event\DataSetModifiedDtRequestEvent;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Provides data from DataSets.
 */
class DataSetProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $this->getLog()->debug('fetchData: DataSetProvider passing to event');
        $this->getDispatcher()->dispatch(
            new DataSetDataRequestEvent($dataProvider),
            DataSetDataRequestEvent::$NAME
        );
        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        if ($durationProvider->getWidget()->getOptionValue('durationIsPerItem', 0) == 1) {
            // Count of rows
            $numItems = $durationProvider->getWidget()->getOptionValue('numItems', 0);

            // Workaround: dataset static (from v3 dataset view) has rowsPerPage instead.
            $rowsPerPage = $durationProvider->getWidget()->getOptionValue('rowsPerPage', 0);
            $itemsPerPage = $durationProvider->getWidget()->getOptionValue('itemsPerPage', 0);

            // If we have paging involved then work out the page count.
            $itemsPerPage = max($itemsPerPage, $rowsPerPage);
            if ($itemsPerPage > 0) {
                $numItems = ceil($numItems / $itemsPerPage);
            }

            $durationProvider->setDuration($durationProvider->getWidget()->calculatedDuration * $numItems);

            $this->getLog()->debug(sprintf(
                'fetchDuration: duration is per item, numItems: %s, rowsPerPage: %s, itemsPerPage: %s',
                $numItems,
                $rowsPerPage,
                $itemsPerPage
            ));
        }
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        // No special cache key requirements.
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        $this->getLog()->debug('fetchData: DataSetProvider passing to modifiedDt request event');
        $dataSetId = $dataProvider->getProperty('dataSetId');
        if ($dataSetId !== null) {
            // Raise an event to get the modifiedDt of this dataSet
            $event = new DataSetModifiedDtRequestEvent($dataSetId);
            $this->getDispatcher()->dispatch($event, DataSetModifiedDtRequestEvent::$NAME);
            return max($event->getModifiedDt(), $dataProvider->getWidgetModifiedDt());
        } else {
            return null;
        }
    }
}
