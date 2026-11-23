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

import type { DatasetFilterInput } from '../DatasetConfig';

import { useDateFormatter } from '@/hooks/useDateFormatter';
import type { FetchDatasetRequest } from '@/services/datasetApi';
import { fetchDataset } from '@/services/datasetApi';
import { resolveLastModified } from '@/utils/date';
import { isValidRegex } from '@/utils/regex';

export const DatasetQueryKeys = {
  all: ['dataset'] as const,
  list: (params: Record<string, unknown>) => [...DatasetQueryKeys.all, 'list', params] as const,
};

interface UseDatasetParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  folderId: number | null;
  advancedFilters: DatasetFilterInput;
  enabled?: boolean;
}

export const useDatasetData = ({
  pagination,
  sorting,
  filter,
  folderId,
  advancedFilters,
  enabled = true,
}: UseDatasetParams) => {
  const { timeZone } = useDateFormatter();

  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    folderId,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: DatasetQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { lastModified, useRegexForName, logicalOperatorName, dataSetId, ...restFilters } =
        advancedFilters;

      const request: FetchDatasetRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...restFilters,
        ...(dataSetId ? { dataSetId: Number(dataSetId) } : {}),
        ...((restFilters.dataSet || filter) && { dataSet: restFilters.dataSet || filter }),
        ...resolveLastModified(lastModified, timeZone),
        ...(useRegexForName && advancedFilters.dataSet && isValidRegex(advancedFilters.dataSet)
          ? { useRegexForName: 1 }
          : {}),
        ...(logicalOperatorName ? { logicalOperatorName } : {}),
      };

      if (typeof folderId === 'number') {
        request.folderId = folderId;
      }

      return fetchDataset(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};
