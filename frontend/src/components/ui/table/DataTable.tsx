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
.*/

import {
  useReactTable,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
  type SortingState,
  type PaginationState,
  type OnChangeFn,
  type RowSelectionState,
  type ColumnPinningState,
  type Column,
  type VisibilityState,
} from '@tanstack/react-table';
import {
  ArrowUp,
  ArrowDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Loader2,
  MoreHorizontal,
} from 'lucide-react';
import { type CSSProperties, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { DataTableViewOptions } from './DataTableViewOptions';

export const getCommonPinningStyles = <TData, TValue>(
  column: Column<TData, TValue>,
): CSSProperties => {
  const isPinned = column.getIsPinned();

  if (!isPinned) {
    return {
      width: column.getSize(),
      position: 'relative',
      zIndex: 0,
      opacity: 1,
    };
  }

  return {
    left: isPinned === 'left' ? `${column.getStart('left')}px` : undefined,
    right: isPinned === 'right' ? `${column.getAfter('right')}px` : undefined,
    opacity: 1,
    position: 'sticky',
    width: column.getSize(),
    zIndex: 20,
  };
};

function getPaginationItems(pageIndex: number, pageCount: number) {
  const current = pageIndex + 1;
  const total = pageCount;
  const delta = 1;

  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }

  const range = [];
  const rangeWithDots = [];
  let l;

  range.push(1);
  for (let i = current - delta; i <= current + delta; i++) {
    if (i < total && i > 1) range.push(i);
  }
  range.push(total);

  for (const i of range) {
    if (l) {
      if (i - l === 2) rangeWithDots.push(l + 1);
      else if (i - l !== 1) rangeWithDots.push('...');
    }
    rangeWithDots.push(i);
    l = i;
  }

  return rangeWithDots;
}

interface DataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[];
  data: TData[];
  pageCount: number;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
  globalFilter: string;
  onGlobalFilterChange: OnChangeFn<string>;
  rowSelection: RowSelectionState;
  onRowSelectionChange: OnChangeFn<RowSelectionState>;
  pageSizeOptions?: number[];
  loading?: boolean;
  selectionActions?: React.ReactNode;
  columnPinning?: ColumnPinningState;
  initialState?: {
    columnVisibility?: VisibilityState;
  };
}

export function DataTable<TData, TValue>({
  columns,
  data,
  pageCount,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
  globalFilter,
  onGlobalFilterChange,
  rowSelection,
  onRowSelectionChange,
  pageSizeOptions = [5, 10, 20, 50],
  loading = false,
  selectionActions,
  columnPinning = { left: [], right: [] },
  initialState,
}: DataTableProps<TData, TValue>) {
  const { t } = useTranslation();

  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
    initialState?.columnVisibility || {},
  );

  const table = useReactTable({
    data,
    columns,
    pageCount,
    state: {
      sorting,
      globalFilter,
      pagination,
      rowSelection,
      columnPinning,
      columnVisibility,
    },
    enableRowSelection: true,
    enableColumnPinning: true,
    onRowSelectionChange,
    onPaginationChange,
    onSortingChange,
    onGlobalFilterChange,
    onColumnVisibilityChange: setColumnVisibility,
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    getCoreRowModel: getCoreRowModel(),
  });

  const selectedCount = Object.keys(rowSelection).length;

  return (
    <div className="flex flex-col gap-y-4">
      {/* TODO: Multi action menu */}
      {selectedCount > 0 && (
        <div className="bg-gray-50 border text-gray-800 px-4 py-3 flex justify-between items-center animate-in fade-in slide-in-from-top-2">
          <span className="font-medium">
            {selectedCount} {selectedCount === 1 ? t('item') : t('items')} {t('selected')}
          </span>
          <div className="flex gap-2">{selectionActions}</div>
        </div>
      )}

      <div className="flex justify-end items-center gap-4">
        <DataTableViewOptions table={table} />
      </div>

      <div className="flex flex-col">
        <div className="border bg-white overflow-hidden relative">
          {loading && (
            <div className="absolute inset-0 bg-white/60 z-50 flex items-center justify-center backdrop-blur-sm transition-all duration-200">
              <div className="flex flex-col items-center">
                <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
                <span className="mt-2 text-gray-500 ">{t('Loading...')}</span>
              </div>
            </div>
          )}

          <div className="overflow-x-auto w-full">
            <table
              className="min-w-full border-separate border-spacing-0 table-fixed"
              style={{ width: table.getTotalSize() }}
            >
              <thead className="bg-gray-50">
                {table.getHeaderGroups().map((headerGroup) => (
                  <tr key={headerGroup.id}>
                    {headerGroup.headers.map((header) => (
                      <th
                        key={header.id}
                        scope="col"
                        className="px-4 py-3 text-gray-500 bg-gray-50"
                        style={getCommonPinningStyles(header.column)}
                        onClick={header.column.getToggleSortingHandler()}
                      >
                        <div
                          className={`flex items-center gap-1 ${header.column.getCanSort() ? 'cursor-pointer select-none hover:text-gray-700' : ''}`}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext())}
                          {{
                            asc: <ArrowUp className="h-3.5 w-3.5" />,
                            desc: <ArrowDown className="h-3.5 w-3.5" />,
                          }[header.column.getIsSorted() as string] ?? null}
                        </div>
                      </th>
                    ))}
                  </tr>
                ))}
              </thead>
              <tbody className="divide-y divide-gray-300">
                {table.getRowModel().rows.length > 0 ? (
                  table.getRowModel().rows.map((row) => (
                    <tr key={row.id} className="">
                      {row.getVisibleCells().map((cell) => (
                        <td
                          key={cell.id}
                          className="px-4 py-4 whitespace-nowrap text-gray-800 bg-white"
                          style={getCommonPinningStyles(cell.column)}
                        >
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </td>
                      ))}
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={columns.length} className="h-24 text-center text-gray-500">
                      {loading ? '' : t('No results!')}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="flex flex-col md:flex-row items-center justify-between gap-4 pt-2">
        <div className="flex items-center gap-2 text-gray-800">
          <span>{t('Rows per page')}:</span>
          <select
            value={table.getState().pagination.pageSize}
            onChange={(e) => table.setPageSize(Number(e.target.value))}
            className="py-1 px-2 pr-8 block"
          >
            {pageSizeOptions.map((pageSize) => (
              <option key={pageSize} value={pageSize}>
                {pageSize}
              </option>
            ))}
          </select>
        </div>

        <nav className="flex items-center gap-x-1">
          <button
            type="button"
            className="py-2 px-2.5 inline-flex justify-center items-center text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none"
            onClick={() => table.setPageIndex(0)}
            disabled={!table.getCanPreviousPage() || loading}
            aria-label={t('First Page')}
          >
            <ChevronsLeft className="h-4 w-4" />
          </button>
          <button
            type="button"
            className="py-2 px-2.5 inline-flex justify-center items-center text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage() || loading}
            aria-label={t('Previous')}
          >
            <ChevronLeft className="h-4 w-4" />
          </button>

          <div className="flex items-center gap-x-1">
            {getPaginationItems(pagination.pageIndex, table.getPageCount()).map((page, index) => {
              if (page === '...') {
                return (
                  <span
                    key={`dots-${index}`}
                    className="min-h-8 min-w-8 flex justify-center items-center text-gray-400"
                  >
                    <MoreHorizontal className="h-4 w-4" />
                  </span>
                );
              }
              const isCurrent = (page as number) === pagination.pageIndex + 1;
              return (
                <button
                  key={page}
                  type="button"
                  onClick={() => table.setPageIndex((page as number) - 1)}
                  disabled={loading}
                  className={`min-h-8 min-w-8 flex justify-center items-center disabled:opacity-50 disabled:pointer-events-none ${
                    isCurrent
                      ? 'bg-gray-200 text-gray-800 focus:bg-gray-300'
                      : 'text-gray-800 hover:bg-gray-100'
                  }`}
                >
                  {page}
                </button>
              );
            })}
          </div>

          <button
            type="button"
            className="min-h-8 min-w-8 py-2 px-2.5 inline-flex justify-center items-center text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage() || loading}
            aria-label={t('Next')}
          >
            <ChevronRight className="h-4 w-4" />
          </button>
          <button
            type="button"
            className="min-h-8 min-w-8 py-2 px-2.5 inline-flex justify-center items-center text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none"
            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
            disabled={!table.getCanNextPage() || loading}
            aria-label={t('Last Page')}
          >
            <ChevronsRight className="h-4 w-4" />
          </button>
        </nav>
      </div>
    </div>
  );
}
