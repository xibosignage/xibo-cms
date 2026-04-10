import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { PaginationState, SortingState } from '@tanstack/react-table';
import type { AxiosError } from 'axios';

import type { CampaignFilterInput } from '../CampaignConfig';

import { fetchCampaigns } from '@/services/campaignApi';
import type { FetchCampaignRequest } from '@/services/campaignApi';

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

      const normalizedTags = advancedFilters.tags?.length
        ? advancedFilters.tags.map((t) => t.tag).join(',')
        : undefined;

      const request: FetchCampaignRequest = {
        start: startOffset,
        length: pagination.pageSize,
        keyword: filter || undefined,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        folderId: folderId ?? undefined,

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
      };

      return fetchCampaigns(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,

    throwOnError: (error: AxiosError) => {
      return error.response?.status ? error.response.status >= 500 : false;
    },
  });
};
