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

import { fetchUnreleasedMedia, fetchUnusedMedia } from '@/services/dashboardApi';

export function useUnusedMedia(pagination: PaginationState, sorting: SortingState) {
  return useQuery({
    queryKey: ['mediaDashboard', 'unused', pagination, sorting],
    queryFn: ({ signal }) =>
      fetchUnusedMedia(
        {
          start: pagination.pageIndex * pagination.pageSize,
          length: pagination.pageSize,
          sortBy: sorting[0]?.id,
          sortDir: sorting[0]?.desc ? 'desc' : 'asc',
        },
        signal,
      ),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
}

export function useUnreleasedMedia(pagination: PaginationState, sorting: SortingState) {
  return useQuery({
    queryKey: ['mediaDashboard', 'unreleased', pagination, sorting],
    queryFn: ({ signal }) =>
      fetchUnreleasedMedia(
        {
          start: pagination.pageIndex * pagination.pageSize,
          length: pagination.pageSize,
          sortBy: sorting[0]?.id,
          sortDir: sorting[0]?.desc ? 'desc' : 'asc',
        },
        signal,
      ),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
}
