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

import { getBaseFilterKeys } from '../DisplayGroupConfig';

import { useDebounce } from '@/hooks/useDebounce';
import { fetchDisplays } from '@/services/displaysApi';
import type { FilterOption } from '@/types/filter';

const PAGE_SIZE = 10;

function toOptions(rows: { displayId: number; display: string }[]): FilterOption[] {
  return rows.map((d) => ({ label: d.display, value: d.displayId.toString() }));
}

export function useDisplayGroupFilterOptions(t: TFunction) {
  const [displayOptions, setDisplayOptions] = useState<FilterOption[]>([]);
  const [displayPage, setDisplayPage] = useState(0);
  const [hasMoreDisplays, setHasMoreDisplays] = useState(false);
  const [isLoadingDisplays, setIsLoadingDisplays] = useState(false);
  const [isLoadingMoreDisplays, setIsLoadingMoreDisplays] = useState(false);
  const [displaySearch, setDisplaySearch] = useState('');
  const debouncedDisplaySearch = useDebounce(displaySearch, 300);

  const [nestedDisplayOptions, setNestedDisplayOptions] = useState<FilterOption[]>([]);
  const [nestedDisplayPage, setNestedDisplayPage] = useState(0);
  const [hasMoreNestedDisplays, setHasMoreNestedDisplays] = useState(false);
  const [isLoadingNestedDisplays, setIsLoadingNestedDisplays] = useState(false);
  const [isLoadingMoreNestedDisplays, setIsLoadingMoreNestedDisplays] = useState(false);
  const [nestedDisplaySearch, setNestedDisplaySearch] = useState('');
  const debouncedNestedDisplaySearch = useDebounce(nestedDisplaySearch, 300);

  useEffect(() => {
    let ignore = false;
    setIsLoadingDisplays(true);
    setDisplayOptions([]);
    setDisplayPage(0);
    setHasMoreDisplays(false);
    fetchDisplays({ start: 0, length: PAGE_SIZE, display: debouncedDisplaySearch || undefined })
      .then((res) => {
        if (ignore) {
          return;
        }
        setDisplayOptions(toOptions(res.rows));
        setHasMoreDisplays(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => {
        if (!ignore) {
          setIsLoadingDisplays(false);
        }
      });
    return () => {
      ignore = true;
    };
  }, [debouncedDisplaySearch]);

  useEffect(() => {
    let ignore = false;
    setIsLoadingNestedDisplays(true);
    setNestedDisplayOptions([]);
    setNestedDisplayPage(0);
    setHasMoreNestedDisplays(false);
    fetchDisplays({
      start: 0,
      length: PAGE_SIZE,
      display: debouncedNestedDisplaySearch || undefined,
    })
      .then((res) => {
        if (ignore) {
          return;
        }
        setNestedDisplayOptions(toOptions(res.rows));
        setHasMoreNestedDisplays(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => {
        if (!ignore) {
          setIsLoadingNestedDisplays(false);
        }
      });
    return () => {
      ignore = true;
    };
  }, [debouncedNestedDisplaySearch]);

  const handleLoadMoreDisplays = () => {
    if (isLoadingMoreDisplays || !hasMoreDisplays) return;
    const nextPage = displayPage + 1;
    setIsLoadingMoreDisplays(true);
    fetchDisplays({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      display: debouncedDisplaySearch || undefined,
    })
      .then((res) => {
        setDisplayOptions((prev) => [...prev, ...toOptions(res.rows)]);
        setDisplayPage(nextPage);
        setHasMoreDisplays(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreDisplays(false));
  };

  const handleLoadMoreNestedDisplays = () => {
    if (isLoadingMoreNestedDisplays || !hasMoreNestedDisplays) return;
    const nextPage = nestedDisplayPage + 1;
    setIsLoadingMoreNestedDisplays(true);
    fetchDisplays({
      start: nextPage * PAGE_SIZE,
      length: PAGE_SIZE,
      display: debouncedNestedDisplaySearch || undefined,
    })
      .then((res) => {
        setNestedDisplayOptions((prev) => [...prev, ...toOptions(res.rows)]);
        setNestedDisplayPage(nextPage);
        setHasMoreNestedDisplays(res.rows.length === PAGE_SIZE);
      })
      .catch(() => {})
      .finally(() => setIsLoadingMoreNestedDisplays(false));
  };

  const filterOptions = getBaseFilterKeys(t).map((item) => {
    if (item.name === 'displayIdDropdown') {
      return {
        ...item,
        options: displayOptions,
        onLoadMore: handleLoadMoreDisplays,
        hasMore: hasMoreDisplays,
        isLoadingMore: isLoadingMoreDisplays,
        isLoading: isLoadingDisplays,
        onSearch: (term: string) => setDisplaySearch(term),
        resolveLabel: (value: string) =>
          fetchDisplays({ start: 0, length: 1, displayId: Number(value) }).then(
            (res) => res.rows[0]?.display ?? value,
          ),
      };
    }
    if (item.name === 'nestedDisplayId') {
      return {
        ...item,
        options: nestedDisplayOptions,
        onLoadMore: handleLoadMoreNestedDisplays,
        hasMore: hasMoreNestedDisplays,
        isLoadingMore: isLoadingMoreNestedDisplays,
        isLoading: isLoadingNestedDisplays,
        onSearch: (term: string) => setNestedDisplaySearch(term),
        resolveLabel: (value: string) =>
          fetchDisplays({ start: 0, length: 1, displayId: Number(value) }).then(
            (res) => res.rows[0]?.display ?? value,
          ),
      };
    }
    return item;
  });

  const isLoading = isLoadingDisplays || isLoadingNestedDisplays;

  return { filterOptions, isLoading };
}
