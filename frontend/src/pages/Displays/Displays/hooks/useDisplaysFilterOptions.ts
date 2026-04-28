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

import { getBaseFilterKeys } from '../DisplaysConfig';

import type { FilterOption } from '@/components/ui/SelectFilter';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchDisplayProfile } from '@/services/displayProfileApi';

export function useDisplaysFilterOptions(t: TFunction) {
  const [filterOptions, setFilterOptions] = useState(() => getBaseFilterKeys(t));
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let ignore = false;

    async function loadDynamicOptions() {
      try {
        const [groupsRes, profilesRes] = await Promise.all([
          fetchDisplayGroups({ start: 0, length: 1000, isDisplaySpecific: 0 }),
          fetchDisplayProfile({ start: 0, length: 1000 }),
        ]);

        if (ignore) {
          return;
        }

        const groupOptions: FilterOption[] = groupsRes.rows.map((group) => ({
          label: group.displayGroup,
          value: group.displayGroupId.toString(),
        }));

        const profileOptions: FilterOption[] = profilesRes.rows.map((profile) => ({
          label: profile.name,
          value: profile.displayProfileId.toString(),
        }));

        const mergedOptions = getBaseFilterKeys(t).map((item) => {
          if (item.name === 'displayGroupId') {
            return { ...item, options: groupOptions };
          }

          if (item.name === 'displayProfileId') {
            return { ...item, options: profileOptions };
          }

          return item;
        });

        setFilterOptions(mergedOptions);
      } catch (error) {
        console.error('Failed to load filter options', error);
      } finally {
        if (!ignore) {
          setIsLoading(false);
        }
      }
    }

    loadDynamicOptions();

    return () => {
      ignore = true;
    };
  }, [t]);

  return { filterOptions, isLoading };
}
