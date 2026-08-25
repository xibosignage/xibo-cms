import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { PaginationState, SortingState } from '@tanstack/react-table';

import type { CampaignFilterInput } from '../CampaignConfig';

import { serializeTags } from '@/components/ui/forms/TagInput';
import { fetchCampaigns } from '@/services/campaignApi';
import type { FetchCampaignRequest } from '@/services/campaignApi';
import { isValidRegex } from '@/utils/regex';

export const campaignQueryKeys = {
  all: ['campaign'] as const,
  list: (params: Record<string, unknown>) => [...campaignQueryKeys.all, 'list', params] as const,
};

interface UseCampaignParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  folderId: number | null;
  advancedFilters: CampaignFilterInput;
  enabled?: boolean;
}

export const useCampaignData = ({
  pagination,
  sorting,
  filter,
  folderId,
  enabled = true,
  advancedFilters,
}: UseCampaignParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    folderId,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: campaignQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { useRegexForName, logicalOperatorName, exactTags, logicalOperator } = advancedFilters;

      const normalizedTags = advancedFilters.tags?.length
        ? serializeTags(advancedFilters.tags)
        : undefined;

      const request: FetchCampaignRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        folderId: folderId ?? undefined,

        ...((advancedFilters.name || filter) && { name: advancedFilters.name || filter }),

        ...(advancedFilters.type && { type: advancedFilters.type }),

        ...(advancedFilters.hasLayouts === '1' && {
          hasLayouts: 1,
        }),
        ...(advancedFilters.hasLayouts === '0' && {
          hasLayouts: 0,
        }),

        ...(advancedFilters.layoutId && {
          layoutId: Number(advancedFilters.layoutId),
        }),

        ...(advancedFilters.cyclePlaybackEnabled && {
          cyclePlaybackEnabled: Number(advancedFilters.cyclePlaybackEnabled),
        }),

        ...(normalizedTags && { tags: normalizedTags }),

        ...(advancedFilters.retired != null && { retired: advancedFilters.retired }),

        ...(useRegexForName && advancedFilters.name && isValidRegex(advancedFilters.name)
          ? { useRegexForName: 1 }
          : {}),
        ...(logicalOperatorName ? { logicalOperatorName } : {}),
        ...(exactTags !== undefined ? { exactTags: exactTags ? 1 : 0 } : {}),
        ...(logicalOperator ? { logicalOperator } : {}),
      };

      return fetchCampaigns(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};
