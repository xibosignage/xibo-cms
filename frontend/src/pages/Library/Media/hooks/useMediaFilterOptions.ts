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

import { useDebounce } from '@/hooks/useDebounce';
import { getBaseFilterKeys } from '@/pages/Library/Media/MediaConfig';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';
import type { FilterOption } from '@/types/filter';

const PAGE_SIZE = 10;

export function useMediaFilterOptions(t: TFunction) {
  const [ownerOptions, setOwnerOptions] = useState<FilterOption[]>([]);
  const [ownerPage, setOwnerPage] = useState(0);
  const [hasMoreOwners, setHasMoreOwners] = useState(false);
  const [isLoadingOwners, setIsLoadingOwners] = useState(false);
  const [isLoadingMoreOwners, setIsLoadingMoreOwners] = useState(false);
  const [ownerSearch, setOwnerSearch] = useState('');
  const debouncedOwnerSearch = useDebounce(ownerSearch, 300);

  const [groupOptions, setGroupOptions] = useState<FilterOption[]>([]);
  const [groupPage, setGroupPage] = useState(0);
  const [hasMoreGroups, setHasMoreGroups] = useState(false);
  const [isLoadingGroups, setIsLoadingGroups] = useState(false);
  const [isLoadingMoreGroups, setIsLoadingMoreGroups] = useState(false);
  const [groupSearch, setGroupSearch] = useState('');
  const debouncedGroupSearch = useDebounce(groupSearch, 300);

  useEffect(() => {
    let ignore = false;
    setIsLoadingOwners(true);
    setOwnerOptions([]);
    setOwnerPage(0);
    setHasMoreOwners(false);
    fetchUsers({ start: 0, length: PAGE_SIZE, userName: debouncedOwnerSearch || undefined })
      .then((res) => {
        if (ignore) {
          return;
        }
        setOwnerOptions(res.rows.map((u) => ({ label: u.userName, value: u.userId.toString() })));
        setHasMoreOwners(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => {
        if (!ignore) {
          setIsLoadingOwners(false);
        }
      });
    return () => {
      ignore = true;
    };
  }, [debouncedOwnerSearch]);

  useEffect(() => {
    let ignore = false;
    setIsLoadingGroups(true);
    setGroupOptions([]);
    setGroupPage(0);
    setHasMoreGroups(false);
    fetchUserGroups({ start: 0, length: PAGE_SIZE, userGroup: debouncedGroupSearch || undefined })
      .then((res) => {
        if (ignore) {
          return;
        }
        setGroupOptions(res.rows.map((g) => ({ label: g.group, value: g.groupId.toString() })));
        setHasMoreGroups(res.rows.length === PAGE_SIZE);
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

  const handleLoadMoreOwners = () => {
    if (isLoadingMoreOwners || !hasMoreOwners) return;
    const nextPage = ownerPage + 1;
    setIsLoadingMoreOwners(true);
    fetchUsers({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      userName: debouncedOwnerSearch || undefined,
    })
      .then((res) => {
        setOwnerOptions((prev) => [
          ...prev,
          ...res.rows.map((u) => ({ label: u.userName, value: u.userId.toString() })),
        ]);
        setOwnerPage(nextPage);
        setHasMoreOwners(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreOwners(false));
  };

  const handleLoadMoreGroups = () => {
    if (isLoadingMoreGroups || !hasMoreGroups) return;
    const nextPage = groupPage + 1;
    setIsLoadingMoreGroups(true);
    fetchUserGroups({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      userGroup: debouncedGroupSearch || undefined,
    })
      .then((res) => {
        setGroupOptions((prev) => [
          ...prev,
          ...res.rows.map((g) => ({ label: g.group, value: g.groupId.toString() })),
        ]);
        setGroupPage(nextPage);
        setHasMoreGroups(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreGroups(false));
  };

  const filterOptions = getBaseFilterKeys(t).map((item) => {
    if (item.name === 'ownerId') {
      return {
        ...item,
        options: ownerOptions,
        onLoadMore: handleLoadMoreOwners,
        hasMore: hasMoreOwners,
        isLoadingMore: isLoadingMoreOwners,
        isLoading: isLoadingOwners,
        onSearch: (term: string) => setOwnerSearch(term),
        resolveLabel: (value: string) =>
          fetchUsers({ start: 0, length: 1, userId: Number(value) }).then(
            (res) => res.rows[0]?.userName ?? value,
          ),
      };
    }

    if (item.name === 'ownerUserGroupId') {
      return {
        ...item,
        options: groupOptions,
        onLoadMore: handleLoadMoreGroups,
        hasMore: hasMoreGroups,
        isLoadingMore: isLoadingMoreGroups,
        isLoading: isLoadingGroups,
        onSearch: (term: string) => setGroupSearch(term),
        resolveLabel: (value: string) =>
          fetchUserGroups({ start: 0, length: 1, userGroupId: Number(value) }).then(
            (res) => res.rows[0]?.group ?? value,
          ),
      };
    }

    return item;
  });

  const isLoading = isLoadingOwners || isLoadingGroups;

  return { filterOptions, isLoading };
}
