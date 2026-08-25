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

import type { UserFilterInput } from '../UsersConfig';

import type { FetchUsersRequest } from '@/services/userApi';
import { fetchUsers } from '@/services/userApi';

export const usersQueryKeys = {
  all: ['users'] as const,
  list: (params: Record<string, unknown>) => [...usersQueryKeys.all, 'list', params] as const,
};

interface UseUsersParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: UserFilterInput;
  enabled?: boolean;
}

export const useUsersData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseUsersParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: usersQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const request: FetchUsersRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...Object.fromEntries(
          Object.entries(advancedFilters).filter(([, v]) => v !== null && v !== ''),
        ),
        ...((advancedFilters.userName || filter) && {
          userName: advancedFilters.userName || filter,
        }),
      };

      return fetchUsers(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};
