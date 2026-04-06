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

import { fetchDatasetData } from '@/services/datasetApi';

interface UseDatasetDataProps {
  datasetId: string;
  pagination: PaginationState;
  sorting: SortingState;
  enabled?: boolean;
}

export function useDatasetData({
  datasetId,
  pagination,
  sorting,
  enabled = true,
}: UseDatasetDataProps) {
  const sort = sorting[0];
  const sortBy = sort?.id;
  const sortDir = sort ? (sort.desc ? 'desc' : 'asc') : undefined;

  return useQuery({
    queryKey: ['datasetData', datasetId, pagination, sorting],
    queryFn: ({ signal }) =>
      fetchDatasetData(datasetId, {
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        sortBy,
        sortDir,
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: enabled && !!datasetId,
  });
}
