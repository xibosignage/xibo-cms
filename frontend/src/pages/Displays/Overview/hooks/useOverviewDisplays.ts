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
import type { PaginationState } from '@tanstack/react-table';

import { getOverviewFilterQueryParams } from '../OverviewFilterConfig';
import type { OverviewFilterState } from '../OverviewFilterConfig';

import { displayQueryKeys } from '@/pages/Displays/Displays/hooks/useDisplaysData';
import { fetchDisplays } from '@/services/displaysApi';

interface UseOverviewDisplaysParams {
  pagination: PaginationState;
  filters: OverviewFilterState;
  enabled?: boolean;
}

// Feeds the Overview page's card grid off the same existing display list
// endpoint the Displays grid uses, just with the filter panel's params
// applied — no client-side bucket logic, per the backend contract.
export function useOverviewDisplays({
  pagination,
  filters,
  enabled = true,
}: UseOverviewDisplaysParams) {
  const filterParams = getOverviewFilterQueryParams(filters);

  return useQuery({
    queryKey: displayQueryKeys.list({ ...pagination, ...filterParams }),

    queryFn: ({ signal }) =>
      fetchDisplays({
        start: pagination.pageIndex * pagination.pageSize,
        length: pagination.pageSize,
        signal,
        ...filterParams,
      }),

    enabled,
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  });
}
