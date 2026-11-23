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

import type { BandwidthFilter } from '../BandwidthConfig';

import { fetchBandwidthReport } from '@/services/bandwidthReportApi';
import { formatDateTime } from '@/utils/date';

export const bandwidthQueryKeys = {
  all: ['bandwidth'] as const,
  report: (params: Record<string, unknown>) => [...bandwidthQueryKeys.all, params] as const,
};

interface UseBandwidthDataParams {
  filter: BandwidthFilter;
  enabled: boolean;
}

export function useBandwidthData({ filter, enabled }: UseBandwidthDataParams) {
  return useQuery({
    queryKey: bandwidthQueryKeys.report(filter as unknown as Record<string, unknown>),

    queryFn: async ({ signal }) => {
      const response = await fetchBandwidthReport({
        fromDt: filter.fromDt ? formatDateTime(new Date(filter.fromDt)) : undefined,
        toDt: filter.toDt ? formatDateTime(new Date(filter.toDt)) : undefined,
        displayId: filter.displayId,
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
