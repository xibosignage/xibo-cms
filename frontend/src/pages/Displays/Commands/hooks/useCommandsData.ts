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

import type { CommandsFilterInput } from '../CommandsConfig';

import { fetchCommands } from '@/services/commandApi';

export const commandQueryKeys = {
  all: ['commands'] as const,
  list: (params: Record<string, unknown>) => [...commandQueryKeys.all, 'list', params] as const,
};

interface UseCommandsParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: CommandsFilterInput;
  enabled?: boolean;
}

export const useCommandsData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseCommandsParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: commandQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      return fetchCommands({
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter || undefined,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        ...(advancedFilters.name ? { command: advancedFilters.name } : {}),
        ...(advancedFilters.code ? { code: advancedFilters.code } : {}),
        ...(advancedFilters.logicalOperatorName
          ? { logicalOperatorName: advancedFilters.logicalOperatorName }
          : {}),
        ...(advancedFilters.useRegexForName ? { useRegexForName: 1 } : {}),
        ...(advancedFilters.logicalOperatorCode
          ? { logicalOperatorCode: advancedFilters.logicalOperatorCode }
          : {}),
        ...(advancedFilters.useRegexForCode ? { useRegexForCode: 1 } : {}),
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
