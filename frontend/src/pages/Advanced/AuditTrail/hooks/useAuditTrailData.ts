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

import type { AuditTrailFilterInput } from '../AuditTrailConfig';

import type { FetchAuditTrailRequest } from '@/services/auditTrailApi';
import { fetchAuditTrail } from '@/services/auditTrailApi';
import { dateKeyToDayBoundary } from '@/utils/date';

export const auditTrailQueryKeys = {
  all: ['auditTrail'] as const,
  list: (params: Record<string, unknown>) => [...auditTrailQueryKeys.all, 'list', params] as const,
};

interface UseAuditTrailParams {
  pagination: PaginationState;
  sorting: SortingState;
  advancedFilters: AuditTrailFilterInput;
  enabled?: boolean;
}

export const useAuditTrailData = ({
  pagination,
  sorting,
  advancedFilters,
  enabled = true,
}: UseAuditTrailParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: auditTrailQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { fromDt, toDt, ...restFilters } = advancedFilters;

      const normalizedFromDt = dateKeyToDayBoundary(fromDt, 'start');
      const normalizedToDt = dateKeyToDayBoundary(toDt, 'end');

      const request: FetchAuditTrailRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        fromDt: normalizedFromDt,
        toDt: normalizedToDt,
        ...restFilters,
      };

      return fetchAuditTrail(request);
    },

    enabled,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};
