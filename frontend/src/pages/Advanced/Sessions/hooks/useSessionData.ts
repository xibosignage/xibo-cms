/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { PaginationState, SortingState } from '@tanstack/react-table';

import type { SessionFilterInput } from '../SessionsConfig';

import { useDateFormatter } from '@/hooks/useDateFormatter';
import type { FetchSessionRequest } from '@/services/sessionApi';
import { fetchSession } from '@/services/sessionApi';
import { resolveLastModified } from '@/utils/date';

export const sessionQueryKeys = {
  all: ['session'] as const,
  list: (params: Record<string, unknown>) => [...sessionQueryKeys.all, 'list', params] as const,
};

interface UseSessionParams {
  pagination: PaginationState;
  sorting: SortingState;
  advancedFilters: SessionFilterInput;
  enabled?: boolean;
}

export const useSessionData = ({
  pagination,
  sorting,
  advancedFilters,
  enabled = true,
}: UseSessionParams) => {
  const { timeZone } = useDateFormatter();

  // Combine settings into one object to create a unique cache key
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: sessionQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { lastModified, ...restFilters } = advancedFilters;
      const { modifiedDateFrom: lastAccessedDateFrom, modifiedDateTo: lastAccessedDateTo } =
        resolveLastModified(lastModified, timeZone);

      const request: FetchSessionRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        lastAccessedDateFrom,
        lastAccessedDateTo,
        ...restFilters,
      } as FetchSessionRequest;

      return fetchSession(request);
    },

    enabled,

    placeholderData: keepPreviousData, // Keep showing previous page's data while the new page loads
    staleTime: 1000 * 60 * 1, // Cache for 1 minute
  });
};
