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

import type { TFunction } from 'i18next';

import { getBaseFilterKeys } from '../LogsConfig';

import { useDisplayGroupSelect } from '@/pages/Reporting/Reports/shared/hooks/useDisplayGroupSelect';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';
import { useUserOptions } from '@/pages/Reporting/Reports/shared/hooks/useUserOptions';

export function useLogsFilterOptions(t: TFunction) {
  const userSelect = useUserOptions();
  const displaySelect = useDisplayOptions();
  const displayGroupSelect = useDisplayGroupSelect();

  const filterOptions = getBaseFilterKeys(t).map((item) => {
    if (item.name === 'userId') {
      return {
        ...item,
        options: userSelect.options,
        isLoading: userSelect.isLoading,
        isLoadingMore: userSelect.isLoadingMore,
        hasMore: userSelect.hasMore,
        onSearch: userSelect.onSearch,
        onLoadMore: userSelect.onLoadMore,
        resolveLabel: userSelect.resolveLabel,
      };
    }

    if (item.name === 'displayId') {
      return {
        ...item,
        options: displaySelect.options,
        isLoading: displaySelect.isLoading,
        isLoadingMore: displaySelect.isLoadingMore,
        hasMore: displaySelect.hasMore,
        onSearch: displaySelect.onSearch,
        onLoadMore: displaySelect.onLoadMore,
        resolveLabel: displaySelect.resolveLabel,
      };
    }

    if (item.name === 'displayGroupId') {
      return {
        ...item,
        options: displayGroupSelect.options,
        isLoading: displayGroupSelect.isLoading,
        isLoadingMore: displayGroupSelect.isLoadingMore,
        hasMore: displayGroupSelect.hasMore,
        onSearch: displayGroupSelect.onSearch,
        onLoadMore: displayGroupSelect.onLoadMore,
        resolveLabel: displayGroupSelect.resolveLabel,
      };
    }

    return item;
  });

  return {
    filterOptions,
    isLoading: userSelect.isLoading || displaySelect.isLoading || displayGroupSelect.isLoading,
  };
}
