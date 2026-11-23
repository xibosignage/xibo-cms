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
        translateLabels: false,
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
        translateLabels: false,
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
        translateLabels: false,
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
