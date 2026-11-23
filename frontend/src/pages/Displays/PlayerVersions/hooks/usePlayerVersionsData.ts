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

import type { PlayerVersionFilterInput } from '../PlayerVersionsConfig';

import { fetchPlayerVersions } from '@/services/playerVersionApi';

export const playerVersionQueryKeys = {
  all: ['playerVersions'] as const,
  list: (params: Record<string, unknown>) =>
    [...playerVersionQueryKeys.all, 'list', params] as const,
};

interface UsePlayerVersionDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: PlayerVersionFilterInput;
  enabled?: boolean;
}

export const usePlayerVersionData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UsePlayerVersionDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: playerVersionQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      return fetchPlayerVersions({
        start: startOffset,
        length: pagination.pageSize,
        playerShowVersion: filter || undefined,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...(advancedFilters.type ? { playerType: advancedFilters.type } : {}),
        ...(advancedFilters.version ? { playerVersion: advancedFilters.version } : {}),
        ...(advancedFilters.code ? { playerCode: Number(advancedFilters.code) } : {}),
      });
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};
