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

import type { ApiRequestsHistoryFilter } from '../ApiRequestsHistoryConfig';

import { fetchApiRequests } from '@/services/apiRequestsApi';
import { formatDateTime } from '@/utils/date';

export const apiRequestsHistoryQueryKeys = {
  all: ['apiRequestsHistory'] as const,
  report: (params: Record<string, unknown>) =>
    [...apiRequestsHistoryQueryKeys.all, params] as const,
};

interface UseApiRequestsHistoryParams {
  filter: ApiRequestsHistoryFilter;
  enabled: boolean;
}

export function useApiRequestsHistoryData({ filter, enabled }: UseApiRequestsHistoryParams) {
  return useQuery({
    queryKey: apiRequestsHistoryQueryKeys.report(filter as unknown as Record<string, unknown>),

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

      const response = await fetchApiRequests({
        reportFilter,
        fromDt,
        toDt,
        type: filter.type,
        userId: filter.userId,
        requestId: filter.requestId || undefined,
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
