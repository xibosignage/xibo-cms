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
