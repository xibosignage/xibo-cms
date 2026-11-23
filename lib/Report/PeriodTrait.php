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

namespace Xibo\Report;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;

/**
 * Trait PeriodTrait
 * @package Xibo\Report
 */
trait PeriodTrait
{
    public function generateHourPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges)
    {

        $periodData = []; // to generate periods table

        // Generate all hours of the period
        foreach ($ranges as $range) {
            $startHour = $start->addHour()->format('U');

            // Remove the period which crossed the end range
            if ($startHour >= $filterRangeEnd) {
                continue;
            }

            // Period start
            $periodData[$range]['start'] = $startHour;
            if ($periodData[$range]['start'] < $filterRangeStart) {
                $periodData[$range]['start'] = $filterRangeStart;
            }

            // Period end
            $periodData[$range]['end'] = $end->addHour()->format('U');
            if ($periodData[$range]['end'] > $filterRangeEnd) {
                $periodData[$range]['end'] = $filterRangeEnd;
            }

            $hourofday = DateFormatHelper::createFromTimestamp($periodData[$range]['start'])->hour;

            // groupbycol =  hour
            $periodData[$range]['groupbycol'] = $hourofday;
        }

        return $periodData;
    }

    public function generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter = null)
    {
        $periodData = []; // to generate periods table

        // Generate all days of the period
        foreach ($ranges as $range) {
            $startDay = $start->addDay()->format('U');

            // Remove the period which crossed the end range
            if ($startDay >= $filterRangeEnd) {
                continue;
            }
            // Period start
            $periodData[$range]['start'] = $startDay;
            if ($periodData[$range]['start'] < $filterRangeStart) {
                $periodData[$range]['start'] = $filterRangeStart;
            }

            // Period end
            $periodData[$range]['end'] = $end->addDay()->format('U');
            if ($periodData[$range]['end'] > $filterRangeEnd) {
                $periodData[$range]['end'] = $filterRangeEnd;
            }

            if ($groupByFilter == 'bydayofweek') {
                $groupbycol = DateFormatHelper::createFromTimestamp($periodData[$range]['start'])->dayOfWeekIso;
            } else {
                $groupbycol =  DateFormatHelper::createFromTimestamp($periodData[$range]['start'])->day;
            }

            // groupbycol =  dayofweek
            $periodData[$range]['groupbycol'] = $groupbycol;
        }
        return $periodData;
    }
}
