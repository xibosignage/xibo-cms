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

import type { StatsFilter } from '../types';
import { resolveReportDateRange } from '../utils/resolveReportDateRange';

import { fetchStatsReport } from '@/services/statsReportApi';

export const statsReportQueryKeys = {
  all: ['statsReport'] as const,
  report: (reportName: string, params: Record<string, unknown>) =>
    [...statsReportQueryKeys.all, reportName, params] as const,
};

interface UseStatsReportDataParams {
  reportName: string;
  filter: StatsFilter;
  enabled: boolean;
}

export function useStatsReportData({ reportName, filter, enabled }: UseStatsReportDataParams) {
  return useQuery({
    queryKey: statsReportQueryKeys.report(reportName, filter as unknown as Record<string, unknown>),

    queryFn: async ({ signal }) => {
      const {
        reportFilter,
        fromDt: statsFromDt,
        toDt: statsToDt,
      } = resolveReportDateRange(filter.reportFilter);

      const response = await fetchStatsReport(reportName, {
        type: filter.type,
        layoutId: filter.layoutId,
        mediaId: filter.mediaId,
        eventTag: filter.eventTag,
        displayId: filter.displayId,
        displayGroupId: filter.displayGroupId.length > 0 ? filter.displayGroupId : undefined,
        reportFilter,
        statsFromDt,
        statsToDt,
        groupByFilter: filter.groupByFilter,
        signal,
      });

      return {
        rows: response.table ?? [],
        metadata: response.metadata,
        recordsTotal: response.recordsTotal,
      };
    },

    enabled,
    staleTime: 0,
    gcTime: 1000 * 60 * 5,
  });
}
