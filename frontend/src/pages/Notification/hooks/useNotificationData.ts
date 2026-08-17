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

import type { NotificationFilterInput } from '../NotificationConfig';

import type { FetchNotificationRequest } from '@/services/notificationApi';
import { fetchMyNotifications, fetchNotifications } from '@/services/notificationApi';

export const notificationQueryKeys = {
  all: ['notification'] as const,
  list: (params: Record<string, unknown>) =>
    [...notificationQueryKeys.all, 'list', params] as const,
  inbox: ['notification', 'inbox'] as const,
};

interface UseNotificationDataParams {
  pagination: PaginationState;
  sorting: SortingState;
  filter: string;
  advancedFilters: NotificationFilterInput;
  enabled?: boolean;
}

export const useNotificationData = ({
  pagination,
  sorting,
  filter,
  advancedFilters,
  enabled = true,
}: UseNotificationDataParams) => {
  const queryParams = {
    pageIndex: pagination.pageIndex,
    pageSize: pagination.pageSize,
    sorting,
    filter,
    ...advancedFilters,
  };

  return useQuery({
    queryKey: notificationQueryKeys.list(queryParams),

    queryFn: async ({ signal }) => {
      const startOffset = pagination.pageIndex * pagination.pageSize;
      const sortBy = sorting?.[0]?.id;
      const sortDir = sorting?.[0]?.desc ? 'desc' : 'asc';

      const request: FetchNotificationRequest = {
        start: startOffset,
        length: pagination.pageSize,
        sortBy,
        sortDir: sorting.length ? sortDir : undefined,
        signal,
        read: advancedFilters.read ?? undefined,
        type: advancedFilters.type || undefined,
        releaseDt: advancedFilters.releaseDt || undefined,
      };

      return fetchNotifications(request);
    },

    enabled,

    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 1,
  });
};

export const useNotificationInbox = () => {
  return useQuery({
    queryKey: notificationQueryKeys.inbox,

    queryFn: async ({ signal }) => fetchMyNotifications({ start: 0, length: 20, signal }),

    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
    refetchInterval: 1000 * 60,
    refetchOnWindowFocus: true,
  });
};
