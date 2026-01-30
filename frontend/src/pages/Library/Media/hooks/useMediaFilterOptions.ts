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

import { useState, useEffect } from 'react';

import type { FilterOption } from '@/components/ui/SelectFilter';
import { BASE_FILTER_KEYS } from '@/pages/Library/Media/MediaConfig';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

export function useMediaFilterOptions() {
  const [filterOptions, setFilterOptions] = useState(BASE_FILTER_KEYS);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let ignore = false;

    async function loadDynamicOptions() {
      try {
        const [usersRes, groupsRes] = await Promise.all([
          fetchUsers({ start: 0, length: 1000 }),
          fetchUserGroups({ start: 0, length: 1000 }),
        ]);

        if (ignore) {
          return;
        }

        const userOptions: FilterOption[] = usersRes.rows.map((user) => ({
          label: user.userName,
          value: user.userId.toString(),
        }));

        const groupOptions: FilterOption[] = groupsRes.rows.map((group) => ({
          label: group.group,
          value: group.groupId.toString(),
        }));

        const mergedOptions = BASE_FILTER_KEYS.map((item) => {
          if (item.name === 'ownerId') {
            return {
              ...item,
              options: [...item.options, ...userOptions],
            };
          }

          if (item.name === 'ownerUserGroupId') {
            return {
              ...item,
              options: [...item.options, ...groupOptions],
            };
          }

          return item;
        });

        setFilterOptions(mergedOptions);
      } catch (error) {
        console.error('Failed to load filter options', error);
      } finally {
        if (!ignore) setIsLoading(false);
      }
    }

    loadDynamicOptions();

    return () => {
      ignore = true;
    };
  }, []);

  return { filterOptions, isLoading };
}
