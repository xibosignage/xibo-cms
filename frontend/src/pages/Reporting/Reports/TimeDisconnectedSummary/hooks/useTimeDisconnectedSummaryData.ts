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

import { useQuery } from '@tanstack/react-query';

import type { TimeDisconnectedSummaryFilter } from '../TimeDisconnectedSummaryConfig';

import { fetchTimeDisconnectedSummary } from '@/services/timeDisconnectedSummaryApi';
import { formatDateTime } from '@/utils/date';

export const timeDisconnectedSummaryQueryKeys = {
  all: ['timeDisconnectedSummary'] as const,
  report: (params: Record<string, unknown>) =>
    [...timeDisconnectedSummaryQueryKeys.all, params] as const,
};

interface UseTimeDisconnectedSummaryParams {
  filter: TimeDisconnectedSummaryFilter;
  enabled: boolean;
}

export function useTimeDisconnectedSummaryData({
  filter,
  enabled,
}: UseTimeDisconnectedSummaryParams) {
  return useQuery({
    queryKey: timeDisconnectedSummaryQueryKeys.report(filter as unknown as Record<string, unknown>),

    queryFn: async ({ signal }) => {
      let reportFilter: string | undefined;
      let fromDt: string | undefined;
      let toDt: string | undefined;

      if (filter.reportFilter.startsWith('range:')) {
        const [from, to] = filter.reportFilter.replace('range:', '').split('|');
        if (from) {
          fromDt = formatDateTime(new Date(from));
        }
        if (to) {
          toDt = formatDateTime(new Date(to));
        }
      } else if (filter.reportFilter) {
        reportFilter = filter.reportFilter;
      }

      const response = await fetchTimeDisconnectedSummary({
        reportFilter,
        fromDt,
        toDt,
        groupBy: filter.groupBy,
        displayId: filter.displayId,
        displayGroupId: filter.displayGroupId.length > 0 ? filter.displayGroupId : undefined,
        tags: filter.tags.length > 0 ? filter.tags.map((tag) => tag.tag).join(',') : undefined,
        exactTags: filter.exactTags,
        onlyLoggedIn: filter.onlyLoggedIn,
        signal,
      });

      return {
        rows: response.table ?? [],
        metadata: response.metadata,
        recordsTotal: response.recordsTotal,
      };
    },

    enabled,
    staleTime: 0,
    gcTime: 1000 * 60 * 5,
  });
}
