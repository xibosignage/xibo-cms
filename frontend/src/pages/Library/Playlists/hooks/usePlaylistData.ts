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

import type { PlaylistFilterInput } from '../PlaylistsConfig';

import type { FetchPlaylistRequest } from '@/services/playlistApi';
import { fetchPlaylist } from '@/services/playlistApi';
import { resolveLastModified } from '@/utils/date';

export const playlistQueryKeys = {
  all: ['playlist'] as const,
  list: (params: Record<string, unknown>) => [...playlistQueryKeys.all, 'list', params] as const,
};

interface UsePlaylistParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  folderId: number | null;
  advancedFilters: PlaylistFilterInput;
}

export const usePlaylistData = ({
  pagination,
  sorting,
  filter,
  folderId,
  advancedFilters,
}: UsePlaylistParams) => {
  // Combine settings into one object to create a unique cache key
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    folderId,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: playlistQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { lastModified, ...restFilters } = advancedFilters;

      const request: FetchPlaylistRequest = {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...restFilters,
        ...resolveLastModified(lastModified),
      } as FetchPlaylistRequest;

      if (typeof folderId === 'number') {
        request.folderId = folderId;
      }

      return fetchPlaylist(request);
    },

    placeholderData: keepPreviousData, // Keep showing previous page's data while the new page loads
    staleTime: 1000 * 60 * 1, // Cache for 1 minute

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
