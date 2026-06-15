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

import type { ModuleTemplateFilterInput } from '../ModuleTemplatesConfig';

import type { FetchModuleTemplatesRequest } from '@/services/moduleTemplatesApi';
import { fetchModuleTemplates } from '@/services/moduleTemplatesApi';

export const moduleTemplateQueryKeys = {
  all: ['moduleTemplate'] as const,
  list: (params: Record<string, unknown>) =>
    [...moduleTemplateQueryKeys.all, 'list', params] as const,
  detail: (id: number) => [...moduleTemplateQueryKeys.all, 'detail', id] as const,
};

interface UseModuleTemplatesDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: ModuleTemplateFilterInput;
  enabled?: boolean;
}

export const useModuleTemplatesData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseModuleTemplatesDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: moduleTemplateQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const start = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const request: FetchModuleTemplatesRequest = {
        id: advancedFilters.id ?? null,
        templateId: advancedFilters.templateId || undefined,
        dataType: advancedFilters.dataType || undefined,
        keyword: filter || undefined,
        start,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
      };

      return fetchModuleTemplates(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 5,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
