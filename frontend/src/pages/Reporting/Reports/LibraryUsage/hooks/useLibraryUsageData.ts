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

import { useQuery } from '@tanstack/react-query';

import type { LibraryUsageFilter } from '../LibraryUsageConfig';

import { fetchLibraryUsage } from '@/services/libraryUsageApi';

export const libraryUsageQueryKeys = {
  all: ['libraryUsage'] as const,
  list: (params: Record<string, unknown>) =>
    [...libraryUsageQueryKeys.all, 'list', params] as const,
};

interface UseLibraryUsageParams {
  filter: LibraryUsageFilter;
  enabled: boolean;
}

export function useLibraryUsageData({ filter, enabled }: UseLibraryUsageParams) {
  return useQuery({
    queryKey: libraryUsageQueryKeys.list({ userId: filter.userId, groupId: filter.groupId }),

    queryFn: async ({ signal }) => {
      const response = await fetchLibraryUsage({
        userId: filter.userId,
        groupId: filter.groupId,
        signal,
      });

      return {
        rows: response.table,
        chart: response.chart,
        metadata: response.metadata,
      };
    },

    enabled,
    staleTime: 0,
    gcTime: 1000 * 60 * 5,
  });
}
