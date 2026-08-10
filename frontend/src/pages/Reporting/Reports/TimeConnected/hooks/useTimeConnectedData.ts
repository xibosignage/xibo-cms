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

import type { DisplayReportRow, TimeConnectedFilter } from '../TimeConnectedConfig';

import { resolveReportDateRange } from '@/pages/Reporting/Reports/shared/utils/resolveReportDateRange';
import type { TimeConnectedTable } from '@/services/timeConnectedApi';
import { fetchTimeConnected } from '@/services/timeConnectedApi';

export function transformData(table: TimeConnectedTable): DisplayReportRow[] {
  const rows: DisplayReportRow[] = [];
  const displayMeta = table.displayMeta ?? {};

  table.displays.forEach((displayGroup, groupIndex) => {
    const periodData = table.timeConnected[groupIndex] ?? {};

    Object.entries(displayGroup).forEach(([displayIdStr, displayName]) => {
      const displayId = Number(displayIdStr);
      const meta = displayMeta[displayIdStr];
      const periods: Array<{ label: string; percent: number }> = [];

      Object.entries(periodData).forEach(([periodLabel, periodDisplays]) => {
        const entry = periodDisplays[displayIdStr];
        if (entry) {
          periods.push({
            label: entry.label || periodLabel,
            percent: Math.min(100, Math.max(0, entry.percent)),
          });
        } else {
          periods.push({ label: periodLabel, percent: 100 });
        }
      });

      const totalPeriods = periods.length;
      const avgUptime =
        totalPeriods > 0 ? periods.reduce((sum, p) => sum + p.percent, 0) / totalPeriods : 0;
      const displayUptime = Math.min(100, Math.max(0, avgUptime));
      const uptimePercent = Math.round(displayUptime * 100) / 100;

      rows.push({
        displayId,
        displayName,
        lastAccessed: meta?.lastAccessed ?? null,
        periods,
        uptimePercent,
        offlinePercent: Math.round((100 - displayUptime) * 100) / 100,
      });
    });
  });

  return rows;
}

export const timeConnectedQueryKeys = {
  all: ['timeConnected'] as const,
  report: (params: Record<string, unknown>) => [...timeConnectedQueryKeys.all, params] as const,
};

interface UseTimeConnectedParams {
  filter: TimeConnectedFilter;
  enabled: boolean;
}

export function useTimeConnectedData({ filter, enabled }: UseTimeConnectedParams) {
  const serverParams = {
    reportFilter: filter.reportFilter,
    fromDt: filter.fromDt,
    toDt: filter.toDt,
    groupByFilter: filter.groupByFilter,
    displaySpecificGroupIds: filter.displaySpecificGroupIds,
    displayGroupIds: filter.displayGroupIds,
  };

  return useQuery({
    queryKey: timeConnectedQueryKeys.report(serverParams),

    queryFn: async ({ signal }) => {
      const { reportFilter, fromDt, toDt } = resolveReportDateRange(filter.reportFilter);

      const displayGroupId = [...filter.displaySpecificGroupIds, ...filter.displayGroupIds];

      const response = await fetchTimeConnected({
        reportFilter,
        fromDt,
        toDt,
        groupByFilter: filter.groupByFilter,
        displayGroupId: displayGroupId.length > 0 ? displayGroupId : undefined,
        signal,
      });

      return {
        rows: transformData(response.table),
        metadata: response.metadata,
      };
    },

    enabled,
    staleTime: 0,
    gcTime: 1000 * 60 * 5,
  });
}
