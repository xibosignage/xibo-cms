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

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { PaginationState, SortingState } from '@tanstack/react-table';
import type { AxiosError } from 'axios';

import type { LogsFilterInput } from '../LogsConfig';

import type { FetchLogsRequest } from '@/services/logApi';
import { fetchLogs } from '@/services/logApi';
import { formatDateTime } from '@/utils/date';

export const logsQueryKeys = {
  all: ['logs'] as const,
  list: (params: Record<string, unknown>) => [...logsQueryKeys.all, 'list', params] as const,
};

interface UseLogsParams {
  pagination: PaginationState;
  sorting: SortingState;
  advancedFilters: LogsFilterInput;
  enabled?: boolean;
}

export const useLogsData = ({
  pagination,
  sorting,
  advancedFilters,
  enabled = true,
}: UseLogsParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: logsQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { fromDt, seconds, intervalType, useRegexForName, ...restFilters } = advancedFilters;

      let normalizedFromDt: string | undefined;
      if (fromDt) {
        const d = new Date(fromDt.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
          normalizedFromDt = formatDateTime(d);
        }
      }

      const request: FetchLogsRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        fromDt: normalizedFromDt,
        seconds: seconds ? Number(seconds) : undefined,
        intervalType: intervalType ? Number(intervalType) : undefined,
        useRegexForName: useRegexForName === '1' ? 1 : undefined,
        ...restFilters,
        displayId: restFilters.displayId ? Number(restFilters.displayId) : undefined,
        userId: restFilters.userId ? Number(restFilters.userId) : undefined,
        displayGroupId: restFilters.displayGroupId ? Number(restFilters.displayGroupId) : undefined,
        excludeLog: restFilters.excludeLog === '1' ? 1 : undefined,
      };

      return fetchLogs(request);
    },

    enabled,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
