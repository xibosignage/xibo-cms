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

import type { ProofOfPlayFilter } from '../ProofOfPlayConfig';

import { resolveReportDateRange } from '@/pages/Reporting/Reports/shared/utils/resolveReportDateRange';
import { fetchProofOfPlay } from '@/services/proofOfPlayApi';

export const proofOfPlayQueryKeys = {
  all: ['proofOfPlay'] as const,
  report: (params: Record<string, unknown>) => [...proofOfPlayQueryKeys.all, params] as const,
};

interface UseProofOfPlayParams {
  filter: ProofOfPlayFilter;
  enabled: boolean;
}

export function useProofOfPlayData({ filter, enabled }: UseProofOfPlayParams) {
  return useQuery({
    queryKey: proofOfPlayQueryKeys.report(filter as unknown as Record<string, unknown>),

    queryFn: async ({ signal }) => {
      const {
        reportFilter,
        fromDt: statsFromDt,
        toDt: statsToDt,
        fromDtTime: statsFromDtTime,
        toDtTime: statsToDtTime,
      } = resolveReportDateRange(filter.reportFilter);

      const response = await fetchProofOfPlay({
        reportFilter,
        statsFromDt,
        statsToDt,
        statsFromDtTime,
        statsToDtTime,
        type: filter.type || undefined,
        layoutId: filter.layoutId.length > 0 ? filter.layoutId : undefined,
        mediaId: filter.mediaId.length > 0 ? filter.mediaId : undefined,
        displayId: filter.displayId,
        displayGroupId: filter.displayGroupId.length > 0 ? filter.displayGroupId : undefined,
        parentCampaignId: filter.parentCampaignId,
        tags: filter.tags.length > 0 ? filter.tags.map((tag) => tag.tag).join(',') : undefined,
        tagsType: filter.tagsType || undefined,
        exactTags: filter.exactTags,
        logicalOperator: filter.logicalOperator || undefined,
        groupBy: filter.groupBy || undefined,
        sortBy: filter.sortBy || undefined,
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
