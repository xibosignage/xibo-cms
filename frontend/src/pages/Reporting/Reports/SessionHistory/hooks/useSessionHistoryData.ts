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

import type { SessionHistoryFilter } from '../SessionHistoryConfig';

import { resolveReportDateRange } from '@/pages/Reporting/Reports/shared/utils/resolveReportDateRange';
import { fetchSessionHistory } from '@/services/sessionHistoryApi';

export const sessionHistoryQueryKeys = {
  all: ['sessionHistory'] as const,
  report: (params: Record<string, unknown>) => [...sessionHistoryQueryKeys.all, params] as const,
};

interface UseSessionHistoryParams {
  filter: SessionHistoryFilter;
  enabled: boolean;
}

export function useSessionHistoryData({ filter, enabled }: UseSessionHistoryParams) {
  return useQuery({
    queryKey: sessionHistoryQueryKeys.report(filter as unknown as Record<string, unknown>),

    queryFn: async ({ signal }) => {
      const { reportFilter, fromDt, toDt } = resolveReportDateRange(filter.reportFilter);

      const response = await fetchSessionHistory({
        reportFilter,
        fromDt,
        toDt,
        type: filter.type,
        userId: filter.userId,
        sessionHistoryId: filter.sessionHistoryId || undefined,
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
