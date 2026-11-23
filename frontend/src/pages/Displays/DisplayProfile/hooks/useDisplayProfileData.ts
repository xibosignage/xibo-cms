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

import type { DisplayProfileFilterInput } from '../DisplayProfileConfig';

import type { FetchDisplayProfileRequest } from '@/services/displayProfileApi';
import { fetchDisplayProfile } from '@/services/displayProfileApi';
import { isValidRegex } from '@/utils/regex';

export const displayProfileQueryKeys = {
  all: ['displayProfile'] as const,
  list: (params: Record<string, unknown>) =>
    [...displayProfileQueryKeys.all, 'list', params] as const,
};

interface UseDisplayProfileParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: DisplayProfileFilterInput;
  enabled?: boolean;
}

export const useDisplayProfileData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseDisplayProfileParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: displayProfileQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { useRegexForName, logicalOperatorName } = advancedFilters;

      const request: FetchDisplayProfileRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...(advancedFilters.displayProfile || filter
          ? { displayProfile: advancedFilters.displayProfile || filter }
          : {}),
        ...(advancedFilters.type
          ? { type: advancedFilters.type as FetchDisplayProfileRequest['type'] }
          : {}),
        ...(useRegexForName &&
        advancedFilters.displayProfile &&
        isValidRegex(advancedFilters.displayProfile)
          ? { useRegexForName: 1 }
          : {}),
        ...(logicalOperatorName ? { logicalOperatorName } : {}),
      };

      return fetchDisplayProfile(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};
