/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
