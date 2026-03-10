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

import { useMutation } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';

import { saveUserPreference } from '@/services/userApi';

interface UseTablePreferencesProps {
  pageKey: string;
  preferences: Record<string, unknown>;
  debounceMs?: number;
  enabled?: boolean;
}

export function useTablePreferences({
  pageKey,
  preferences,
  debounceMs = 500,
  enabled = true,
}: UseTablePreferencesProps) {
  const { mutate } = useMutation({
    mutationFn: saveUserPreference,
  });

  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const isFirstRun = useRef(true);

  const preferencesString = JSON.stringify(preferences);

  const flushSaveRef = useRef<() => void>(() => {});

  useEffect(() => {
    flushSaveRef.current = () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
        mutate({
          option: pageKey,
          value: {
            time: Date.now(),
            ...JSON.parse(preferencesString),
          },
        });
        timeoutRef.current = null;
      }
    };
  }, [preferencesString, pageKey, mutate]);

  useEffect(() => {
    return () => {
      flushSaveRef.current();
    };
  }, []);

  useEffect(() => {
    if (!enabled) {
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
      mutate({
        option: pageKey,
        value: {
          time: Date.now(),
          ...JSON.parse(preferencesString),
        },
      });
      timeoutRef.current = null;
    }, debounceMs);

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, [preferencesString, pageKey, debounceMs, mutate, enabled]);
}
