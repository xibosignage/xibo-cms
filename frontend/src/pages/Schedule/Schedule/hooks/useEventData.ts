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

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { PaginationState, SortingState } from '@tanstack/react-table';

import type { EventFilterInput } from '../EventsConfig';

import type { FetchEventRequest } from '@/services/eventApi';
import { fetchEvent } from '@/services/eventApi';
import type { Event } from '@/types/event';
import { isValidRegex } from '@/utils/regex';

const ALL_EVENTS_PAGE_SIZE = 200;

export const eventQueryKeys = {
  all: ['event'] as const,
  list: (params: Record<string, unknown>) => [...eventQueryKeys.all, 'list', params] as const,
};

interface UseEventParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: EventFilterInput;
  enabled?: boolean;
}

export const useEventData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseEventParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: eventQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;

      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const { useRegexForName, logicalOperatorName } = advancedFilters;

      const request: FetchEventRequest = {
        start: startOffset,
        length: pagination.pageSize,
        name: filter || advancedFilters.name || undefined,
        eventTypeId: advancedFilters.eventTypeId ?? undefined,
        campaignId: advancedFilters.layoutCampaignId ?? advancedFilters.campaignId ?? undefined,
        displaySpecificGroupIds: advancedFilters.displaySpecificGroupIds ?? undefined,
        displayGroupIds: advancedFilters.displayGroupIds ?? undefined,
        geoAware: advancedFilters.geoAware ?? undefined,
        recurring: advancedFilters.recurring ?? undefined,
        directSchedule: advancedFilters.directSchedule ?? undefined,
        sharedSchedule: advancedFilters.sharedSchedule ?? undefined,
        fromDt: advancedFilters.fromDt ?? undefined,
        toDt: advancedFilters.toDt ?? undefined,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        ...(useRegexForName && advancedFilters.name && isValidRegex(advancedFilters.name)
          ? { useRegexForName: 1 }
          : {}),
        ...(logicalOperatorName ? { logicalOperatorName } : {}),
      };

      return fetchEvent(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};

interface UseAllEventParams {
  advancedFilters: EventFilterInput;
  enabled?: boolean;
}

export const useAllEventData = ({ advancedFilters, enabled = true }: UseAllEventParams) => {
  const queryParams = { all: true, ...advancedFilters };

  return useQuery({
    queryKey: eventQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const { useRegexForName, logicalOperatorName } = advancedFilters;

      const baseRequest: Omit<FetchEventRequest, 'start' | 'length' | 'signal'> = {
        name: advancedFilters.name || undefined,
        eventTypeId: advancedFilters.eventTypeId ?? undefined,
        campaignId: advancedFilters.layoutCampaignId ?? advancedFilters.campaignId ?? undefined,
        displaySpecificGroupIds: advancedFilters.displaySpecificGroupIds ?? undefined,
        displayGroupIds: advancedFilters.displayGroupIds ?? undefined,
        geoAware: advancedFilters.geoAware ?? undefined,
        recurring: advancedFilters.recurring ?? undefined,
        directSchedule: advancedFilters.directSchedule ?? undefined,
        sharedSchedule: advancedFilters.sharedSchedule ?? undefined,
        fromDt: advancedFilters.fromDt ?? undefined,
        toDt: advancedFilters.toDt ?? undefined,
        ...(useRegexForName && advancedFilters.name && isValidRegex(advancedFilters.name)
          ? { useRegexForName: 1 }
          : {}),
        ...(logicalOperatorName ? { logicalOperatorName } : {}),
      };

      let rows: Event[] = [];
      let totalCount = 0;

      do {
        const response = await fetchEvent({
          ...baseRequest,
          start: rows.length,
          length: ALL_EVENTS_PAGE_SIZE,
          signal,
        });
        totalCount = response.totalCount;
        if (response.rows.length === 0) {
          break;
        }
        rows = rows.concat(response.rows);
      } while (rows.length < totalCount);

      return { rows, totalCount };
    },

    enabled,

    staleTime: 1000 * 60 * 1,
  });
};
