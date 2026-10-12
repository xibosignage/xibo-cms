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

import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { vi } from 'vitest';

import { renderColumnStates, renderColumnText } from '@/testUtils/columnContract';

/**
 * Shared set-up for the contract tests in this folder.
 *
 * Grid rows are built inline as plain objects shaped like the API response (see each case), so
 * these helpers take `object` rows and cast them for the column getter. That keeps every case
 * about the values that matter, without importing a row type for each of ~30 grids.
 */

export const t = ((key: string) => key) as unknown as TFunction;

/** A recognisable CMS formatter: a cell that uses it shows `formatted:<value>`. */
export const formatDateTime = (value: unknown) =>
  `formatted:${value instanceof Date ? value.toISOString() : String(value)}`;

/**
 * Props for a column getter. Real values for the props a cell reads; anything else (the row-action
 * callbacks) becomes an inert `vi.fn()`, because the contract never renders the actions column.
 *
 * Pass every prop a cell actually reads. Unset props come back as a truthy `vi.fn()`, so a missing
 * `formatDateTime` would make dates render blank and a missing `selectedTagIds` would crash Tags.
 */
export const propsWith = <P>(own: Record<string, unknown> = {}): P => {
  const values: Record<string, unknown> = {
    t,
    formatDateTime,
    timezone: 'UTC',
    timeZone: 'UTC',
    locale: 'en',
    selectedTagIds: [],
    reportDescriptionMap: {},
    ...own,
  };
  const stubs = new Map<string | symbol, unknown>();
  return new Proxy(values, {
    get: (target, key) => {
      if (key in target) return target[key as string];
      if (!stubs.has(key)) stubs.set(key, vi.fn());
      return stubs.get(key);
    },
    has: () => true,
  }) as P;
};

/** Render one column for several labelled rows; returns each row's markup. */
export const statesOf = <T>(
  columns: ColumnDef<T>[],
  columnId: string,
  rows: Record<string, object>,
) => renderColumnStates(columns, columnId, rows as Record<string, T>);

/** The text a user reads in one column for one row. */
export const textOf = <T>(columns: ColumnDef<T>[], columnId: string, row: object) =>
  renderColumnText(columns, columnId, row as T);

/** A unix timestamp (seconds) and the SQL datetime string the API sends for the same moment. */
export const EPOCH = 1758614400;
export const SQL_DATETIME = '2025-09-23 08:00:00';
