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
import { useEffect, useState } from 'react';

import { getOverviewFilterKeys } from '../OverviewFilterConfig';

import { useDebounce } from '@/hooks/useDebounce';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import type { FilterOption } from '@/types/filter';

const GROUP_PAGE_SIZE = 50;

// Scoped-down sibling of Displays/hooks/useDisplaysFilterOptions.ts — the
// Overview filter panel only needs the display group picker resolved
// dynamically, everything else is a static option list.
export function useOverviewFilterOptions(t: TFunction) {
  const [groupOptions, setGroupOptions] = useState<FilterOption[]>([]);
  const [isLoadingGroups, setIsLoadingGroups] = useState(false);
  const [groupSearch, setGroupSearch] = useState('');
  const debouncedGroupSearch = useDebounce(groupSearch, 300);

  useEffect(() => {
    let ignore = false;
    setIsLoadingGroups(true);
    fetchDisplayGroups({
      start: 0,
      length: GROUP_PAGE_SIZE,
      isDisplaySpecific: 0,
      displayGroup: debouncedGroupSearch || undefined,
    })
      .then((res) => {
        if (ignore) {
          return;
        }
        setGroupOptions(
          res.rows.map((g) => ({ label: g.displayGroup, value: g.displayGroupId.toString() })),
        );
      })
      .catch(() => {})
      .finally(() => {
        if (!ignore) {
          setIsLoadingGroups(false);
        }
      });
    return () => {
      ignore = true;
    };
  }, [debouncedGroupSearch]);

  const filterOptions = getOverviewFilterKeys(t).map((item) => {
    if (item.name === 'displayGroupId') {
      return {
        ...item,
        options: groupOptions,
        isLoading: isLoadingGroups,
        onSearch: (term: string) => setGroupSearch(term),
      };
    }
    return item;
  });

  return { filterOptions };
}
