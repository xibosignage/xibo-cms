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

import type { ReportScheduleFilterInput } from '../ReportSchedulesConfig';

import type { FetchReportSchedulesRequest } from '@/services/reportScheduleApi';
import { fetchReportSchedules } from '@/services/reportScheduleApi';
import { isValidRegex } from '@/utils/regex';

export const reportScheduleQueryKeys = {
  all: ['reportSchedule'] as const,
  list: (params: Record<string, unknown>) =>
    [...reportScheduleQueryKeys.all, 'list', params] as const,
};

interface UseReportScheduleDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: ReportScheduleFilterInput;
  enabled?: boolean;
}

export const useReportScheduleData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseReportScheduleDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    advancedFilters,
  };

  return useQuery({
    queryKey: reportScheduleQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const request: FetchReportSchedulesRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
      };

      if (advancedFilters.name || filter) {
        request.name = advancedFilters.name || filter;
      }

      if (advancedFilters.name && advancedFilters.logicalOperatorName) {
        request.logicalOperatorName = advancedFilters.logicalOperatorName;
      }

      if (
        advancedFilters.useRegexForName &&
        advancedFilters.name &&
        isValidRegex(advancedFilters.name)
      ) {
        request.useRegexForName = 1;
      }

      if (advancedFilters.userId) {
        request.userId = Number(advancedFilters.userId);
      }

      if (advancedFilters.reportScheduleId != null) {
        request.reportScheduleId = advancedFilters.reportScheduleId;
      }

      if (advancedFilters.reportName) {
        request.reportName = advancedFilters.reportName;
      }

      if (advancedFilters.onlyMySchedules === '1') {
        request.onlyMySchedules = 1;
      }

      return fetchReportSchedules(request);
    },

    enabled,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  });
};
