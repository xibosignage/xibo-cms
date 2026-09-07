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

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState, type Dispatch, type SetStateAction } from 'react';

import { fetchUserPreference, saveUserPreference } from '@/services/userApi';

const COLLAPSED_PREF_KEY = 'sidebar.isCollapsed';
const COLLAPSED_STORAGE_KEY = 'xibo.sidebar.isCollapsed';

const OPEN_MENUS_PREF_KEY = 'sidebar.openMenus';
const OPEN_MENUS_STORAGE_KEY = 'xibo.sidebar.openMenus';

function safeLocalStorageGet(key: string): string | null {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function safeLocalStorageSet(key: string, value: string): void {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Ignore
  }
}

function readLocalCollapsed(): boolean {
  return safeLocalStorageGet(COLLAPSED_STORAGE_KEY) === 'true';
}

function writeLocalCollapsed(value: boolean): void {
  safeLocalStorageSet(COLLAPSED_STORAGE_KEY, String(value));
}

export function useSidebarCollapsed(): [boolean, (value: boolean) => void] {
  const queryClient = useQueryClient();
  const [isCollapsed, setIsCollapsedState] = useState<boolean>(readLocalCollapsed);
  // Guards against a slow fetch resolving after the user has already toggled
  // the value and overwriting their change back to the old server value.
  const hasReconciled = useRef(false);

  const { data: savedPref, isSuccess } = useQuery({
    queryKey: ['userPref', COLLAPSED_PREF_KEY],
    queryFn: () => fetchUserPreference<boolean>(COLLAPSED_PREF_KEY),
    staleTime: Infinity,
  });

  useEffect(() => {
    if (isSuccess && !hasReconciled.current) {
      hasReconciled.current = true;
      if (typeof savedPref === 'boolean') {
        setIsCollapsedState(savedPref);
        writeLocalCollapsed(savedPref);
      }
    }
  }, [isSuccess, savedPref]);

  const { mutate: savePref } = useMutation({ mutationFn: saveUserPreference });

  const setIsCollapsed = (value: boolean) => {
    hasReconciled.current = true;
    setIsCollapsedState(value);
    writeLocalCollapsed(value);
    savePref({ option: COLLAPSED_PREF_KEY, value });
    queryClient.setQueryData(['userPref', COLLAPSED_PREF_KEY], value);
  };

  return [isCollapsed, setIsCollapsed];
}

function readLocalOpenMenus(): Set<string> {
  const raw = safeLocalStorageGet(OPEN_MENUS_STORAGE_KEY);
  if (!raw) {
    return new Set();
  }
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? new Set(parsed) : new Set();
  } catch {
    return new Set();
  }
}

function writeLocalOpenMenus(value: Set<string>): void {
  safeLocalStorageSet(OPEN_MENUS_STORAGE_KEY, JSON.stringify(Array.from(value)));
}

export function useSidebarOpenMenus(): [Set<string>, Dispatch<SetStateAction<Set<string>>>] {
  const queryClient = useQueryClient();
  const [openMenus, setOpenMenusState] = useState<Set<string>>(readLocalOpenMenus);

  const {
    data: savedPref,
    isSuccess,
    isError,
  } = useQuery({
    queryKey: ['userPref', OPEN_MENUS_PREF_KEY],
    queryFn: () => fetchUserPreference<string[]>(OPEN_MENUS_PREF_KEY),
    staleTime: Infinity,
  });

  // Used below to hold off saving until this fetch has settled — otherwise
  // the route's auto-expand-on-mount can debounce-save before the fetch
  // resolves, overwriting the real saved menus with a current-route-only set.
  const isSettled = isSuccess || isError;

  // A union-merge only ever adds menus back, so the one race worth guarding
  // against is the user closing a menu before this fetch resolves — without
  // this, a stale "it was open on another device" value could reopen it.
  const hasReconciled = useRef(false);

  useEffect(() => {
    if (isSuccess && !hasReconciled.current) {
      hasReconciled.current = true;
      if (Array.isArray(savedPref) && savedPref.length > 0) {
        setOpenMenusState((prev) => new Set([...prev, ...savedPref]));
      }
    }
  }, [isSuccess, savedPref]);

  const setOpenMenus: Dispatch<SetStateAction<Set<string>>> = (updater) => {
    setOpenMenusState((prev) => {
      const next =
        typeof updater === 'function'
          ? (updater as (p: Set<string>) => Set<string>)(prev)
          : updater;
      if (next.size < prev.size) {
        hasReconciled.current = true;
      }
      return next;
    });
  };

  const { mutate: savePref } = useMutation({ mutationFn: saveUserPreference });

  const openMenusKey = JSON.stringify(Array.from(openMenus));
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const isFirstRun = useRef(true);

  useEffect(() => {
    writeLocalOpenMenus(openMenus);
  }, [openMenus, openMenusKey]);

  const flushSaveRef = useRef<() => void>(() => {});

  useEffect(() => {
    flushSaveRef.current = () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
        const value = Array.from(openMenus);
        savePref({ option: OPEN_MENUS_PREF_KEY, value });
        queryClient.setQueryData(['userPref', OPEN_MENUS_PREF_KEY], value);
        timeoutRef.current = null;
      }
    };
  }, [openMenus, openMenusKey, savePref, queryClient]);

  useEffect(() => {
    return () => {
      flushSaveRef.current();
    };
  }, []);

  useEffect(() => {
    if (!isSettled) {
      return;
    }

    if (isFirstRun.current) {
      isFirstRun.current = false;
      return;
    }

    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
      const value = Array.from(openMenus);
      savePref({ option: OPEN_MENUS_PREF_KEY, value });
      queryClient.setQueryData(['userPref', OPEN_MENUS_PREF_KEY], value);
      timeoutRef.current = null;
    }, 500);

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, [openMenus, openMenusKey, isSettled, savePref, queryClient]);

  return [openMenus, setOpenMenus];
}
