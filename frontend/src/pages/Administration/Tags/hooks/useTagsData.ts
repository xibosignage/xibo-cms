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

import type { TagFilterInput } from '../TagsConfig';

import type { FetchTagsRequest } from '@/services/tagApi';
import { fetchTags } from '@/services/tagApi';

export const tagQueryKeys = {
  all: ['tag'] as const,
  list: (params: Record<string, unknown>) => [...tagQueryKeys.all, 'list', params] as const,
};

interface UseTagsDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: TagFilterInput;
  enabled?: boolean;
}

export const useTagsData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseTagsDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    advancedFilters,
  };

  return useQuery({
    queryKey: tagQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      // isSystem null → All: skip the isSystem filter by sending allTags=1
      // isSystem 1/0 → Yes/No: send isSystem directly, no allTags
      const isSystemValue = advancedFilters.isSystem;
      const allTags = isSystemValue === null ? 1 : undefined;
      const isSystem = isSystemValue !== null ? isSystemValue : undefined;

      // haveOptions null → All: omit param entirely
      // haveOptions 1 → Yes (IS NOT NULL), 0 → No (IS NULL)
      const haveOptions = advancedFilters.haveOptions ?? undefined;

      const request: FetchTagsRequest = {
        start: startOffset,
        length: pagination.pageSize,
        tagId: advancedFilters.tagId ?? undefined,
        tag: advancedFilters.tag || filter || undefined,
        logicalOperatorName: advancedFilters.logicalOperatorName ?? undefined,
        useRegexForName: advancedFilters.useRegexForName ?? undefined,
        allTags,
        isSystem,
        haveOptions,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
      };

      return fetchTags(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });
};
