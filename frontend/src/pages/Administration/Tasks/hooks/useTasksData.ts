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

import type { TaskFilterInput } from '../TasksConfig';

import type { FetchTaskRequest } from '@/services/taskApi';
import { fetchTasks } from '@/services/taskApi';

export const taskQueryKeys = {
  all: ['task'] as const,
  list: (params: Record<string, unknown>) => [...taskQueryKeys.all, 'list', params] as const,
};

interface UseTaskDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: TaskFilterInput;
  enabled?: boolean;
}

export const useTaskData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseTaskDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    advancedFilters,
  };

  return useQuery({
    queryKey: taskQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const request: FetchTaskRequest = {
        start: startOffset,
        length: pagination.pageSize,
        name: advancedFilters.name || filter || undefined,
        logicalOperatorName: advancedFilters.logicalOperatorName ?? undefined,
        useRegexForName: advancedFilters.useRegexForName ?? undefined,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
      };

      return fetchTasks(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });
};
