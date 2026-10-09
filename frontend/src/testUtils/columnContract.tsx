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

import { createTable, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type { ColumnDef, Table } from '@tanstack/react-table';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import {
  getExportCellValue,
  getExportHeader,
  isExportableColumn,
} from '@/components/ui/table/exportCsv';

/**
 * Grid column contract helpers.
 *
 * A grid cell can only be trusted if values that MEAN different things LOOK different, and values
 * that mean the same thing look the same. These helpers render one column's real `cell` renderer
 * through a real TanStack table (so `info.getValue()` and `row.original` behave exactly as they do
 * in the page) and hand back the rendered markup. Tests compare that markup between rows.
 *
 * Comparing whole markup keeps the contract independent of how a cell draws itself — a tick icon,
 * a badge, or text all work — so no test needs to know a CSS class or an icon name.
 */

const columnIdOf = <T,>(column: ColumnDef<T>): string | undefined =>
  column.id ?? ('accessorKey' in column ? String(column.accessorKey) : undefined);

function renderCell<T>(
  columns: ColumnDef<T>[],
  columnId: string,
  row: T,
): { markup: string; text: string } {
  const column = columns.find((c) => columnIdOf(c) === columnId);
  if (!column) {
    throw new Error(
      `No column "${columnId}". Available: ${columns.map(columnIdOf).filter(Boolean).join(', ')}`,
    );
  }

  function CellProbe() {
    const table = useReactTable({
      data: [row],
      columns: [column!],
      getCoreRowModel: getCoreRowModel(),
    });
    const cell = table.getRowModel().rows[0]!.getVisibleCells()[0]!;
    return <>{flexRender(cell.column.columnDef.cell, cell.getContext())}</>;
  }

  const { container, unmount } = render(
    <MemoryRouter>
      <CellProbe />
    </MemoryRouter>,
  );
  const rendered = { markup: container.innerHTML, text: container.textContent ?? '' };
  unmount();
  return rendered;
}

/** Render a single column's cell for one row and return its markup. */
export const renderColumnCell = <T,>(columns: ColumnDef<T>[], columnId: string, row: T): string =>
  renderCell(columns, columnId, row).markup;

/** Render a single column's cell for one row and return only the text a user would read. */
export const renderColumnText = <T,>(columns: ColumnDef<T>[], columnId: string, row: T): string =>
  renderCell(columns, columnId, row).text.trim();

/** Render the same column for several rows, keyed by a label the test chooses. */
export function renderColumnStates<T>(
  columns: ColumnDef<T>[],
  columnId: string,
  rows: Record<string, T>,
): Record<string, string> {
  return Object.fromEntries(
    Object.entries(rows).map(([label, row]) => [label, renderColumnCell(columns, columnId, row)]),
  );
}

/** A headless TanStack table over the given rows, with the same defaults the grids use. */
function buildTable<T>(columns: ColumnDef<T>[], data: T[]): Table<T> {
  const table = createTable<T>({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    state: {},
    onStateChange: () => {},
    renderFallbackValue: null,
  });
  table.setOptions((prev) => ({ ...prev, state: table.initialState }));
  return table;
}

/**
 * The value one column exports to CSV for one row, computed by the same function the grid's CSV
 * button uses (`getExportCellValue` in components/ui/table/exportCsv.ts).
 */
export function exportColumnValue<T>(columns: ColumnDef<T>[], columnId: string, row: T): string {
  const cell = buildTable(columns, [row])
    .getRowModel()
    .rows[0]!.getAllCells()
    .find((c) => c.column.id === columnId);
  if (!cell) throw new Error(`No column "${columnId}"`);
  return getExportCellValue(cell);
}

/** Every column the CSV export includes, with its CSV header and whether it declares getExportValue. */
export function exportedColumns<T>(columns: ColumnDef<T>[]) {
  return buildTable(columns, [])
    .getAllLeafColumns()
    .filter((column) => isExportableColumn(column.id, column.columnDef.meta))
    .map((column) => ({
      id: column.id,
      header: getExportHeader(column),
      declaresExportValue: Boolean(column.columnDef.meta?.getExportValue),
    }));
}

/** The ids of the columns a user can sort by clicking their header. */
export function sortableColumnIds<T>(columns: ColumnDef<T>[]): string[] {
  return buildTable(columns, [])
    .getAllLeafColumns()
    .filter((column) => column.getCanSort())
    .map((column) => column.id);
}
