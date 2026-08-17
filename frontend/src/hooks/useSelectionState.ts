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

import type { OnChangeFn, RowSelectionState } from '@tanstack/react-table';
import { useEffect, useState } from 'react';

interface UseSelectionStateOptions<T> {
  list: T[];
  getRowId: (row: T) => string;
}

export function useSelectionState<T>({ list, getRowId }: UseSelectionStateOptions<T>) {
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, T>>({});

  const onRowSelectionChange: OnChangeFn<RowSelectionState> = (updaterOrValue) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      list.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  // Prunes selectionCache whenever rowSelection changes for any reason, including
  // consumers that call the raw setRowSelection setter directly (e.g. bulk-action
  // success handlers), so the cache never grows unbounded with stale entries.
  useEffect(() => {
    setSelectionCache((prev) => {
      let changed = false;
      const next: Record<string, T> = {};
      Object.keys(prev).forEach((id) => {
        const item = prev[id];
        if (rowSelection[id] && item) {
          next[id] = item;
        } else {
          changed = true;
        }
      });
      return changed ? next : prev;
    });
  }, [rowSelection]);

  const getAllSelectedItems = (): T[] => {
    const liveById = new Map(list.map((item) => [getRowId(item), item]));
    return Object.keys(rowSelection)
      .map((id) => liveById.get(id) ?? selectionCache[id])
      .filter((item): item is T => !!item);
  };

  return { rowSelection, setRowSelection, onRowSelectionChange, getRowId, getAllSelectedItems };
}
