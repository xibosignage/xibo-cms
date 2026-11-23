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

import { fetchDatasetRss } from '@/services/datasetApi';

interface UseDatasetRssDataProps {
  datasetId: string;
  pagination: PaginationState;
  filter: string;
  sorting: SortingState;
  enabled?: boolean;
}

export function useDatasetRssData({
  datasetId,
  pagination,
  filter,
  sorting,
  enabled = true,
}: UseDatasetRssDataProps) {
  const sort = sorting[0];
  const sortBy = sort?.id;
  const sortDir = sort ? (sort.desc ? 'desc' : 'asc') : undefined;

  return useQuery({
    queryKey: ['datasetRss', datasetId, pagination, filter, sorting],
    queryFn: ({ signal }) =>
      fetchDatasetRss(datasetId, {
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        title: filter || undefined,
        sortBy,
        sortDir,
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: enabled && !!datasetId,
  });
}
