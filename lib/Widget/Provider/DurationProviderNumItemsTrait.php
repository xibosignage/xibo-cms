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
