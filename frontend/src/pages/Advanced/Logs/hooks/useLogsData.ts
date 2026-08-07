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

import { keepPreviousData, useInfiniteQuery } from '@tanstack/react-query';
import type { AxiosError } from 'axios';

import type { LogsFilterInput } from '../LogsConfig';

import type { FetchLogsRequest } from '@/services/logApi';
import { fetchLogs } from '@/services/logApi';
import { formatDateTime } from '@/utils/date';

export const logsQueryKeys = {
  all: ['logs'] as const,
  list: (params: Record<string, unknown>) => [...logsQueryKeys.all, 'list', params] as const,
};

// Set a limit for a single batch of results, and show a message
// if we need to request more if we go over that value
export const LOG_BATCH_SIZE = 5000;

interface UseLogsParams {
  advancedFilters: LogsFilterInput;
  anchorTime: string | null;
  enabled?: boolean;
}

export const useLogsData = ({ advancedFilters, anchorTime, enabled = true }: UseLogsParams) => {
  return useInfiniteQuery({
    queryKey: logsQueryKeys.list({
      ...advancedFilters,
      anchorTime,
    }),

    initialPageParam: 0,

    queryFn: async ({ pageParam, signal }) => {
      const { fromDt, seconds, intervalType, useRegexForName, ...restFilters } = advancedFilters;

      let normalizedFromDt: string | undefined;
      if (fromDt) {
        const d = new Date(fromDt.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
          normalizedFromDt = formatDateTime(d);
        }
      } else if (anchorTime) {
        normalizedFromDt = anchorTime;
      }

      const request: FetchLogsRequest = {
        start: pageParam,
        length: LOG_BATCH_SIZE,
        sortBy: 'logId',
        sortDir: 'desc',
        signal,
        fromDt: normalizedFromDt,
        seconds: seconds ? Number(seconds) : undefined,
        intervalType: intervalType ? Number(intervalType) : undefined,
        useRegexForName: useRegexForName ? 1 : undefined,
        ...restFilters,
        userId: restFilters.userId ? Number(restFilters.userId) : undefined,
        displayId: restFilters.displayId ? Number(restFilters.displayId) : undefined,
        displayGroupId: restFilters.displayGroupId ? Number(restFilters.displayGroupId) : undefined,
        excludeLog: restFilters.excludeLog ? 1 : undefined,
      };

      return fetchLogs(request);
    },

    getNextPageParam: (lastPage, allPages) => {
      const loaded = allPages.reduce((sum, page) => sum + page.rows.length, 0);
      return loaded < lastPage.totalCount ? loaded : undefined;
    },

    enabled,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
