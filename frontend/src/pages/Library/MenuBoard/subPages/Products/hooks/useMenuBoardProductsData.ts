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

import type { MenuBoardProductFilterInput } from '../MenuBoardProductsConfig';

import { fetchMenuBoardProducts } from '@/services/menuBoardApi';
import { isValidRegex } from '@/utils/regex';

export const MenuBoardProductQueryKeys = {
  all: ['menuBoardProduct'] as const,
  list: (menuCategoryId: string | number, params: Record<string, unknown>) =>
    [...MenuBoardProductQueryKeys.all, 'list', String(menuCategoryId), params] as const,
};

interface UseMenuBoardProductsDataParams {
  menuCategoryId: string | number;
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: MenuBoardProductFilterInput;
  enabled?: boolean;
}

export const useMenuBoardProductsData = ({
  menuCategoryId,
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseMenuBoardProductsDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: MenuBoardProductQueryKeys.list(menuCategoryId, queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';
      const { menuProductId, name, useRegexForName, code, availability } = advancedFilters;

      return fetchMenuBoardProducts(menuCategoryId, {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        menuProductId: menuProductId ? Number(menuProductId) : undefined,
        name: name || filter || undefined,
        ...(useRegexForName && name && isValidRegex(name) ? { useRegexForName: 1 } : {}),
        code: code || undefined,
        availability: availability !== '' ? Number(availability) : undefined,
      });
    },

    enabled: enabled && !!menuCategoryId,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });
};
