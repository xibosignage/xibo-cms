/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

import { dateKeyToDayBoundary } from '@/utils/date';

export interface ReportDateRange {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
}

// Report date-range filters are either a named preset (passed through to the server as-is)
// or a `range:<fromKey>|<toKey>` pair of calendar-day keys picked in DateRangeFilter.
export function resolveReportDateRange(reportFilter: string): ReportDateRange {
  if (reportFilter.startsWith('range:')) {
    const [from, to] = reportFilter.replace('range:', '').split('|');
    return {
      fromDt: dateKeyToDayBoundary(from, 'start'),
      toDt: dateKeyToDayBoundary(to, 'end'),
    };
  }

  if (reportFilter) {
    return { reportFilter };
  }

  return {};
}
