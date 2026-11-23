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

import { dateKeyToDayBoundary } from '@/utils/date';

export interface ReportDateRange {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
  fromDtTime?: string;
  toDtTime?: string;
}

// Report date-range filters are either a named preset (passed through to the server as-is)
// or a `range:<fromKey>|<toKey>` pair of calendar-day keys picked in DateRangeFilter, where
// each key may carry an optional `T<HH:mm>` time-of-day suffix (only when the picker had
// showTimePicker enabled).
export function resolveReportDateRange(reportFilter: string): ReportDateRange {
  if (reportFilter.startsWith('range:')) {
    const [fromRaw, toRaw] = reportFilter.replace('range:', '').split('|');
    const [from, fromDtTime] = fromRaw?.split('T') ?? [];
    const [to, toDtTime] = toRaw?.split('T') ?? [];
    return {
      fromDt: dateKeyToDayBoundary(from, 'start'),
      toDt: dateKeyToDayBoundary(to, 'end'),
      fromDtTime,
      toDtTime,
    };
  }

  if (reportFilter) {
    return { reportFilter };
  }

  return {};
}
