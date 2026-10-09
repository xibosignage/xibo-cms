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

import type { Cell, Column } from '@tanstack/react-table';

// Columns injected by the table itself (selection checkbox, row actions) have no
// exportable/printable data of their own.
export const NON_PRINTABLE_COLUMNS = ['tableSelection', 'tableActions'];

export const isExportableColumn = (columnId: string, meta?: { excludeFromExport?: boolean }) =>
  !NON_PRINTABLE_COLUMNS.includes(columnId) && !meta?.excludeFromExport;

/** The CSV header for a column: its header text, or its id when the header is not plain text. */
export const getExportHeader = <TData>(column: Column<TData, unknown>): string =>
  typeof column.columnDef.header === 'string' ? column.columnDef.header : column.id;

/** The unquoted CSV value for one cell. */
export const getExportCellValue = <TData>(cell: Cell<TData, unknown>): string => {
  const getExportValue = cell.column.columnDef.meta?.getExportValue;

  if (getExportValue) {
    // Column declares its own CSV representation (e.g. formatted dates,
    // status labels) separately from its on-screen `cell` renderer.
    return getExportValue(cell.row.original) ?? '';
  }

  const value = cell.getValue();

  // Some tables have an object row so we need to convert it to JSON string
  if (value === null || value === undefined) return '';
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
};

/** Quote a value as one CSV field, doubling any quotes inside it. */
export const toCsvField = (raw: string): string => `"${raw.replace(/"/g, '""')}"`;
