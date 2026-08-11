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

import type { DisplayAlertsFilter } from '../DisplayAlertsConfig';

import { resolveReportDateRange } from '@/pages/Reporting/Reports/shared/utils/resolveReportDateRange';
import { fetchDisplayAlerts } from '@/services/displayAlertsApi';

export const displayAlertsQueryKeys = {
  all: ['displayAlerts'] as const,
  report: (params: Record<string, unknown>) => [...displayAlertsQueryKeys.all, params] as const,
};

interface UseDisplayAlertsParams {
  filter: DisplayAlertsFilter;
  enabled: boolean;
}

export function useDisplayAlertsData({ filter, enabled }: UseDisplayAlertsParams) {
  return useQuery({
    queryKey: displayAlertsQueryKeys.report(filter as unknown as Record<string, unknown>),

    queryFn: async ({ signal }) => {
      const { reportFilter, fromDt, toDt } = resolveReportDateRange(filter.reportFilter);

      const response = await fetchDisplayAlerts({
        reportFilter,
        fromDt,
        toDt,
        eventType: filter.eventType || undefined,
        displayId: filter.displayId,
        displayGroupId: filter.displayGroupId.length > 0 ? filter.displayGroupId : undefined,
        tags: filter.tags.length > 0 ? filter.tags.map((tag) => tag.tag).join(',') : undefined,
        exactTags: filter.exactTags,
        logicalOperator: filter.logicalOperator || undefined,
        onlyLoggedIn: filter.onlyLoggedIn,
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
