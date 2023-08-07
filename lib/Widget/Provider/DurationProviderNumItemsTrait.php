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

namespace Xibo\Widget\Provider;

/**
 * A trait providing the duration for widgets using numItems, durationIsPerItem and itemsPerPage
 */
trait DurationProviderNumItemsTrait
{
    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        $this->getLog()->debug('fetchDuration: DurationProviderNumItemsTrait');

        // Take some default action to cover the majourity of region specific widgets
        // Duration can depend on the number of items per page for some widgets
        // this is a legacy way of working, and our preference is to use elements
        $numItems = $durationProvider->getWidget()->getOptionValue('numItems', 15);

        if ($durationProvider->getWidget()->getOptionValue('durationIsPerItem', 0) == 1 && $numItems > 1) {
            // If we have paging involved then work out the page count.
            $itemsPerPage = $durationProvider->getWidget()->getOptionValue('itemsPerPage', 0);
            if ($itemsPerPage > 0) {
                $numItems = ceil($numItems / $itemsPerPage);
            }

            $durationProvider->setDuration($durationProvider->getWidget()->calculatedDuration * $numItems);
        }
        return $this;
    }
}
