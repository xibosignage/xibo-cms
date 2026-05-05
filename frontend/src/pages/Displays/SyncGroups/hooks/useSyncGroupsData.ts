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

import type { SyncGroupsFilterInput } from '../SyncGroupsConfig';

import { fetchSyncGroups } from '@/services/syncGroupApi';

export const syncGroupQueryKeys = {
  all: ['syncGroups'] as const,
  list: (params: Record<string, unknown>) => [...syncGroupQueryKeys.all, 'list', params] as const,
};

interface UseSyncGroupParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: SyncGroupsFilterInput;
  enabled?: boolean;
}

export const useSyncGroupData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseSyncGroupParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: syncGroupQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      return fetchSyncGroups({
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter || undefined,
        ...(advancedFilters.leadDisplayId ? { leadDisplayId: advancedFilters.leadDisplayId } : {}),
        signal,
      });
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
