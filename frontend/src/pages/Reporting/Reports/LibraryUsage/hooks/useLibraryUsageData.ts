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

import { useQuery } from '@tanstack/react-query';

import type { LibraryUsageFilter } from '../LibraryUsageConfig';

import { fetchLibraryUsage } from '@/services/libraryUsageApi';

export const libraryUsageQueryKeys = {
  all: ['libraryUsage'] as const,
  report: (params: Record<string, unknown>) => [...libraryUsageQueryKeys.all, params] as const,
};

interface UseLibraryUsageParams {
  filter: LibraryUsageFilter;
  enabled: boolean;
}

export function useLibraryUsageData({ filter, enabled }: UseLibraryUsageParams) {
  return useQuery({
    queryKey: libraryUsageQueryKeys.report({ userId: filter.userId, groupId: filter.groupId }),

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
