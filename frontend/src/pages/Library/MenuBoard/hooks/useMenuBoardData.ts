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

import type { MenuBoardFilterInput } from '../MenuBoardConfig';

import type { FetchMenuBoardRequest } from '@/services/menuBoardApi';
import { fetchMenuBoard } from '@/services/menuBoardApi';
import { resolveLastModified } from '@/utils/date';
import { isValidRegex } from '@/utils/regex';

export const MenuBoardQueryKeys = {
  all: ['menuBoard'] as const,
  list: (params: Record<string, unknown>) => [...MenuBoardQueryKeys.all, 'list', params] as const,
};

interface UseMenuBoardDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  folderId: number | null;
  advancedFilters: MenuBoardFilterInput;
  enabled?: boolean;
}

export const useMenuBoardData = ({
  pagination,
  sorting,
  filter,
  folderId,
  advancedFilters,
  enabled = true,
}: UseMenuBoardDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    folderId,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: MenuBoardQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { name, menuId, userId, code, lastModified, useRegexForName, logicalOperatorName } =
        advancedFilters;

      const request: FetchMenuBoardRequest = {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        name: name || undefined,
        menuId: menuId ? Number(menuId) : undefined,
        userId: userId ? Number(userId) : undefined,
        code: code || undefined,
        ...resolveLastModified(lastModified),
        ...(useRegexForName && name && isValidRegex(name) ? { useRegexForName: 1 } : {}),
        ...(logicalOperatorName ? { logicalOperatorName } : {}),
      };

      if (typeof folderId === 'number') {
        request.folderId = folderId;
      }

      return fetchMenuBoard(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
