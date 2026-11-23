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
