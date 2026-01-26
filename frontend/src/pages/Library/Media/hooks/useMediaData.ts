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
.*/

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { PaginationState, SortingState } from '@tanstack/react-table';
import type { AxiosError } from 'axios';

import { fetchMedia } from '@/services/mediaApi';

export const mediaQueryKeys = {
  all: ['media'] as const,
  list: (params: Record<string, unknown>) => [...mediaQueryKeys.all, 'list', params] as const,
};

interface FilterInput {
  type: string;
  owner: string;
  userGroup: string;
  orientation: string;
  retired: string;
  lastModified: string;
}

interface UseMediaParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: FilterInput;
}

export const useMediaData = ({ pagination, sorting, filter, advancedFilters }: UseMediaParams) => {
  // Combine settings into one object to create a unique cache key
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: mediaQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      return fetchMedia({
        start: startOffset,
        length: pagination.pageSize,
        media: filter,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...advancedFilters,
      });
    },

    placeholderData: keepPreviousData, // Keep showing previous page's data while the new page loads
    staleTime: 1000 * 60 * 1, // Cache for 1 minute

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
