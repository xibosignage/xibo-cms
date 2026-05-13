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

import { getBaseFilterKeys } from '../DatasetConfig';

import { useDebounce } from '@/hooks/useDebounce';
import { fetchUsers } from '@/services/userApi';
import type { FilterOption } from '@/types/filter';

const PAGE_SIZE = 10;

export function useDatasetFilterOptions(t: TFunction) {
  const [ownerOptions, setOwnerOptions] = useState<FilterOption[]>([]);
  const [ownerPage, setOwnerPage] = useState(0);
  const [hasMoreOwners, setHasMoreOwners] = useState(false);
  const [isLoadingOwners, setIsLoadingOwners] = useState(false);
  const [isLoadingMoreOwners, setIsLoadingMoreOwners] = useState(false);
  const [ownerSearch, setOwnerSearch] = useState('');
  const debouncedOwnerSearch = useDebounce(ownerSearch, 300);

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

  const filterOptions = getBaseFilterKeys(t).map((item) => {
    if (item.name === 'userId') {
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

    return item;
  });

  return { filterOptions, isLoading: isLoadingOwners };
}
