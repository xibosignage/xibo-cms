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

import { useDebounce } from '@/hooks/useDebounce';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchDisplayProfile, fetchDisplayProfileById } from '@/services/displayProfileApi';
import type { FilterOption } from '@/types/filter';

const PAGE_SIZE = 10;

export function useDisplaysFilterOptions(t: TFunction) {
  const [groupOptions, setGroupOptions] = useState<FilterOption[]>([]);
  const [groupPage, setGroupPage] = useState(0);
  const [hasMoreGroups, setHasMoreGroups] = useState(false);
  const [isLoadingGroups, setIsLoadingGroups] = useState(false);
  const [isLoadingMoreGroups, setIsLoadingMoreGroups] = useState(false);
  const [groupSearch, setGroupSearch] = useState('');
  const debouncedGroupSearch = useDebounce(groupSearch, 300);

  const [profileOptions, setProfileOptions] = useState<FilterOption[]>([]);
  const [profilePage, setProfilePage] = useState(0);
  const [hasMoreProfiles, setHasMoreProfiles] = useState(false);
  const [isLoadingProfiles, setIsLoadingProfiles] = useState(false);
  const [isLoadingMoreProfiles, setIsLoadingMoreProfiles] = useState(false);
  const [profileSearch, setProfileSearch] = useState('');
  const debouncedProfileSearch = useDebounce(profileSearch, 300);

  useEffect(() => {
    let ignore = false;
    setIsLoadingGroups(true);
    setGroupOptions([]);
    setGroupPage(0);
    setHasMoreGroups(false);
    fetchDisplayGroups({
      start: 0,
      length: PAGE_SIZE,
      isDisplaySpecific: 0,
      keyword: debouncedGroupSearch || undefined,
    })
      .then((res) => {
        if (ignore) {
          return;
        }
        setGroupOptions(
          res.rows.map((g) => ({ label: g.displayGroup, value: g.displayGroupId.toString() })),
        );
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

  useEffect(() => {
    let ignore = false;
    setIsLoadingProfiles(true);
    setProfileOptions([]);
    setProfilePage(0);
    setHasMoreProfiles(false);
    fetchDisplayProfile({
      start: 0,
      length: PAGE_SIZE,
      keyword: debouncedProfileSearch || undefined,
    })
      .then((res) => {
        if (ignore) {
          return;
        }
        setProfileOptions(
          res.rows.map((p) => ({ label: p.name, value: p.displayProfileId.toString() })),
        );
        setHasMoreProfiles(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => {
        if (!ignore) {
          setIsLoadingProfiles(false);
        }
      });
    return () => {
      ignore = true;
    };
  }, [debouncedProfileSearch]);

  const handleLoadMoreGroups = () => {
    if (isLoadingMoreGroups || !hasMoreGroups) return;
    const nextPage = groupPage + 1;
    setIsLoadingMoreGroups(true);
    fetchDisplayGroups({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      isDisplaySpecific: 0,
      keyword: debouncedGroupSearch || undefined,
    })
      .then((res) => {
        setGroupOptions((prev) => [
          ...prev,
          ...res.rows.map((g) => ({ label: g.displayGroup, value: g.displayGroupId.toString() })),
        ]);
        setGroupPage(nextPage);
        setHasMoreGroups(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreGroups(false));
  };

  const handleLoadMoreProfiles = () => {
    if (isLoadingMoreProfiles || !hasMoreProfiles) return;
    const nextPage = profilePage + 1;
    setIsLoadingMoreProfiles(true);
    fetchDisplayProfile({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      keyword: debouncedProfileSearch || undefined,
    })
      .then((res) => {
        setProfileOptions((prev) => [
          ...prev,
          ...res.rows.map((p) => ({ label: p.name, value: p.displayProfileId.toString() })),
        ]);
        setProfilePage(nextPage);
        setHasMoreProfiles(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreProfiles(false));
  };

  const filterOptions = getBaseFilterKeys(t).map((item) => {
    if (item.name === 'displayGroupId') {
      return {
        ...item,
        options: groupOptions,
        onLoadMore: handleLoadMoreGroups,
        hasMore: hasMoreGroups,
        isLoadingMore: isLoadingMoreGroups,
        isLoading: isLoadingGroups,
        onSearch: (term: string) => setGroupSearch(term),
        resolveLabel: (value: string) =>
          fetchDisplayGroups({
            start: 0,
            length: 1,
            displayGroupId: Number(value),
            isDisplaySpecific: 0,
          }).then((res) => res.rows[0]?.displayGroup ?? value),
      };
    }

    if (item.name === 'displayProfileId') {
      return {
        ...item,
        options: profileOptions,
        onLoadMore: handleLoadMoreProfiles,
        hasMore: hasMoreProfiles,
        isLoadingMore: isLoadingMoreProfiles,
        isLoading: isLoadingProfiles,
        onSearch: (term: string) => setProfileSearch(term),
        resolveLabel: (value: string) => fetchDisplayProfileById(Number(value)).then((p) => p.name),
      };
    }

    return item;
  });

  const isLoading = isLoadingGroups || isLoadingProfiles;

  return { filterOptions, isLoading };
}
