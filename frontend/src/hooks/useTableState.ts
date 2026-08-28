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

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { PaginationState, SortingState, VisibilityState } from '@tanstack/react-table';
import { useState, useEffect, useRef } from 'react';

import type { ViewMode } from '@/components/ui/table/types';
import { useUserContext } from '@/context/UserContext';
import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import { isPreferenceEnabled } from '@/utils/preferences';

const FOLDER_ID_GLOBAL_KEY = 'globalSelectedFolderId';

export interface TablePreferences<TFilters> {
  pagination: PaginationState;
  sorting: SortingState;
  columnVisibility: VisibilityState;
  viewMode: ViewMode;
  globalFilter: string;
  filterInputs: TFilters;
  folderId?: number | null;
}

export function useTableState<TFilters>(
  pageKey: string,
  defaultState: TablePreferences<TFilters>,
  debounceMs = 500,
) {
  const queryClient = useQueryClient();
  const { user } = useUserContext();
  const rememberFolderGlobally = isPreferenceEnabled(
    user?.settings?.rememberFolderTreeStateGlobally,
    true,
  );
  const folderIdKey = rememberFolderGlobally ? FOLDER_ID_GLOBAL_KEY : `${pageKey}_folderId`;

  const [pagination, setPagination] = useState<PaginationState>(defaultState.pagination);
  const [sorting, setSorting] = useState<SortingState>(defaultState.sorting);
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
    defaultState.columnVisibility,
  );
  const [viewMode, setViewMode] = useState<ViewMode>(defaultState.viewMode);

  const [globalFilter, setGlobalFilter] = useState<string>(defaultState.globalFilter);

  const [debouncedFilter, setDebouncedFilter] = useState<string>(defaultState.globalFilter);

  const [filterInputs, setFilterInputs] = useState<TFilters>(defaultState.filterInputs);

  const [folderId, setFolderIdState] = useState<number | null>(defaultState.folderId ?? null);
  const [isFolderIdHydrated, setIsFolderIdHydrated] = useState(false);
  const hasInteractedWithFolderRef = useRef(false);

  useEffect(() => {
    let isActive = true;
    hasInteractedWithFolderRef.current = false;
    setIsFolderIdHydrated(false);

    // A rejection here must not block hydration forever, so errors are
    // swallowed and isFolderIdHydrated is always set in .finally().
    fetchUserPreference<number | null>(folderIdKey)
      .then((stored) => {
        if (
          isActive &&
          !hasInteractedWithFolderRef.current &&
          stored !== null &&
          stored !== undefined
        ) {
          setFolderIdState(stored);
        }
      })
      .catch(() => {})
      .finally(() => {
        if (isActive) {
          setIsFolderIdHydrated(true);
        }
      });

    return () => {
      isActive = false;
    };
  }, [folderIdKey]);

  const setFolderId = (value: number | null) => {
    hasInteractedWithFolderRef.current = true;
    setFolderIdState(value);
    saveUserPreference({ option: folderIdKey, value });
  };

  const [isHydrated, setIsHydrated] = useState(false);

  const { data: savedPrefs, isSuccess: hasLoadedPrefs } = useQuery({
    queryKey: ['userPref', pageKey],
    queryFn: () => fetchUserPreference<Partial<TablePreferences<TFilters>>>(pageKey),
    staleTime: Infinity,
  });

  useEffect(() => {
    if (hasLoadedPrefs && !isHydrated) {
      if (savedPrefs) {
        if (savedPrefs.sorting) {
          setSorting(savedPrefs.sorting);
        }
        if (savedPrefs.columnVisibility) {
          setColumnVisibility(savedPrefs.columnVisibility);
        }
        if (savedPrefs.viewMode) {
          setViewMode(savedPrefs.viewMode);
        }
        if (savedPrefs.filterInputs) {
          setFilterInputs(savedPrefs.filterInputs);
        }
      }
      setIsHydrated(true);
    }
  }, [savedPrefs, hasLoadedPrefs, isHydrated]);

  useEffect(() => {
    if (!isHydrated) {
      return;
    }

    const timer = setTimeout(() => setDebouncedFilter(globalFilter), 500);
    return () => clearTimeout(timer);
  }, [globalFilter, isHydrated]);

  const { mutate } = useMutation({ mutationFn: saveUserPreference });

  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const isFirstSave = useRef(true);

  const currentPrefs = {
    sorting,
    columnVisibility,
    viewMode,
    filterInputs,
  };

  const prefsString = JSON.stringify(currentPrefs);

  const flushSaveRef = useRef<() => void>(() => {});

  useEffect(() => {
    flushSaveRef.current = () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
        const newValue = { time: Date.now(), ...JSON.parse(prefsString) };
        mutate({ option: pageKey, value: newValue });
        queryClient.setQueryData(['userPref', pageKey], newValue);
        timeoutRef.current = null;
      }
    };
  }, [prefsString, pageKey, mutate, queryClient]);

  useEffect(() => {
    return () => {
      flushSaveRef.current();
    };
  }, []);

  useEffect(() => {
    if (!isHydrated) {
      return;
    }

    if (isFirstSave.current) {
      isFirstSave.current = false;
      return;
    }

    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
      const newValue = { time: Date.now(), ...JSON.parse(prefsString) };
      mutate({ option: pageKey, value: newValue });
      queryClient.setQueryData(['userPref', pageKey], newValue);
      timeoutRef.current = null;
    }, debounceMs);

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, [prefsString, pageKey, debounceMs, mutate, isHydrated, queryClient]);

  return {
    pagination,
    setPagination,
    sorting,
    setSorting: ((updater) => {
      setSorting(updater);
      setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    }) as typeof setSorting,
    columnVisibility,
    setColumnVisibility,
    viewMode,
    setViewMode,
    globalFilter,
    setGlobalFilter,
    debouncedFilter,
    filterInputs,
    setFilterInputs,
    folderId,
    setFolderId,
    isHydrated: isHydrated && isFolderIdHydrated,
  };
}
