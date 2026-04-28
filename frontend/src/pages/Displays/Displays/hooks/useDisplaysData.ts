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

import type { DisplayFilterInput } from '../DisplaysConfig';

import type { FetchDisplaysRequest } from '@/services/displaysApi';
import { fetchDisplays } from '@/services/displaysApi';

export const displayQueryKeys = {
  all: ['display'] as const,
  list: (params: Record<string, unknown>) => [...displayQueryKeys.all, 'list', params] as const,
};

interface UseDisplaysParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: DisplayFilterInput;
  folderId?: number | null;
  enabled?: boolean;
}

export const useDisplaysData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  folderId,
  enabled = true,
}: UseDisplaysParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    folderId,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: displayQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const request: FetchDisplaysRequest = {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...(advancedFilters.mediaInventoryStatus
          ? { mediaInventoryStatus: advancedFilters.mediaInventoryStatus }
          : {}),
        ...(advancedFilters.loggedIn !== null && advancedFilters.loggedIn !== undefined
          ? { loggedIn: advancedFilters.loggedIn }
          : {}),
        ...(advancedFilters.authorised !== null && advancedFilters.authorised !== undefined
          ? { authorised: advancedFilters.authorised }
          : {}),
        ...(advancedFilters.xmrRegistered !== null && advancedFilters.xmrRegistered !== undefined
          ? { xmrRegistered: advancedFilters.xmrRegistered }
          : {}),
        ...(advancedFilters.clientType ? { clientType: advancedFilters.clientType } : {}),
        ...(advancedFilters.displayGroupId
          ? { displayGroupId: advancedFilters.displayGroupId }
          : {}),
        ...(advancedFilters.displayProfileId
          ? { displayProfileId: advancedFilters.displayProfileId }
          : {}),
        ...(advancedFilters.orientation ? { orientation: advancedFilters.orientation } : {}),
        ...(advancedFilters.commercialLicence !== null &&
        advancedFilters.commercialLicence !== undefined
          ? { commercialLicence: advancedFilters.commercialLicence }
          : {}),
        ...(advancedFilters.isPlayerSupported !== null &&
        advancedFilters.isPlayerSupported !== undefined
          ? { isPlayerSupported: advancedFilters.isPlayerSupported }
          : {}),
        ...(advancedFilters.clientCode ? { clientCode: advancedFilters.clientCode } : {}),
        ...(advancedFilters.customId ? { customId: advancedFilters.customId } : {}),
        ...(advancedFilters.macAddress ? { macAddress: advancedFilters.macAddress } : {}),
        ...(advancedFilters.clientAddress ? { clientAddress: advancedFilters.clientAddress } : {}),
        ...(advancedFilters.lastAccessed ? { lastAccessed: advancedFilters.lastAccessed } : {}),
        ...(folderId ? { folderId } : {}),
      };

      return fetchDisplays(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
