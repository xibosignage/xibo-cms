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

import type { ResolutionFilterInput } from '../ResolutionsConfig';

import type { FetchResolutionRequest } from '@/services/resolutionApi';
import { fetchResolution } from '@/services/resolutionApi';

export const resolutionQueryKeys = {
  all: ['resolution'] as const,
  list: (params: Record<string, unknown>) => [...resolutionQueryKeys.all, 'list', params] as const,
};

interface UseResolutionParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: ResolutionFilterInput;
  enabled?: boolean;
}

export const useResolutionData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseResolutionParams) => {
  // Combine settings into one object to create a unique cache key
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: resolutionQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { enabled: filterEnabled } = advancedFilters;

      const isEnabled = filterEnabled === 1 ? 1 : filterEnabled === 0 ? 0 : undefined;

      const request: FetchResolutionRequest = {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...(isEnabled !== undefined && { enabled: isEnabled }),
      } as FetchResolutionRequest;

      return fetchResolution(request);
    },

    enabled,

    placeholderData: keepPreviousData, // Keep showing previous page's data while the new page loads
    staleTime: 1000 * 60 * 1, // Cache for 1 minute

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
