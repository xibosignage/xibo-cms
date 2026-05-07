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

import type { MenuBoardCategoryFilterInput } from '../MenuBoardCategoriesConfig';

import { fetchMenuBoardCategories } from '@/services/menuBoardApi';

export const MenuBoardCategoryQueryKeys = {
  all: ['menuBoardCategory'] as const,
  list: (menuId: string | number, params: Record<string, unknown>) =>
    [...MenuBoardCategoryQueryKeys.all, 'list', String(menuId), params] as const,
};

interface UseMenuBoardCategoriesDataParams {
  menuId: string | number;
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: MenuBoardCategoryFilterInput;
  enabled?: boolean;
}

export const useMenuBoardCategoriesData = ({
  menuId,
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseMenuBoardCategoriesDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: MenuBoardCategoryQueryKeys.list(menuId, queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';
      const { menuCategoryId, code } = advancedFilters;

      return fetchMenuBoardCategories(menuId, {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        menuCategoryId: menuCategoryId ? Number(menuCategoryId) : undefined,
        code: code || undefined,
      });
    },

    enabled: enabled && !!menuId,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
