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

import type { TemplatesFilterInput } from '../TemplatesConfig';

import type { FetchTemplateRequest } from '@/services/templatesApi';
import { fetchTemplates } from '@/services/templatesApi';

export const templateQueryKeys = {
  all: ['template'] as const,
  list: (params: Record<string, unknown>) => [...templateQueryKeys.all, 'list', params] as const,
};

interface UseTemplatesParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  folderId: number | null;
  advancedFilters: TemplatesFilterInput;
  enabled?: boolean;
}

export const useTemplateData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  folderId,
  enabled = true,
}: UseTemplatesParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    folderId,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: templateQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { tags, ...restFilters } = advancedFilters;

      const normalizedTags = tags && tags.length > 0 ? tags.map((t) => t.tag).join(',') : undefined;

      const request: FetchTemplateRequest = {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter || undefined,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...restFilters,
        ...(normalizedTags ? { tags: normalizedTags } : {}),
      };

      if (typeof folderId === 'number') {
        request.folderId = folderId;
      }

      return fetchTemplates(request);
    },

    enabled,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
