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
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Loader2,
  MoreHorizontal,
  ChevronUp,
  ChevronDown,
  ChevronsUpDown,
} from 'lucide-react';
import { type CSSProperties, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { DataTableBulkAction } from './DataTableBulkActions';
import { DataTableBulkActions } from './DataTableBulkActions';
import { DataTableOptions } from './DataTableOptions';

import { CheckboxCell } from '@/components/ui/table/cells';

interface DataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[];
  enableSelection?: boolean;
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
  bulkActions?: DataTableBulkAction<TData>[];
  columnPinning?: ColumnPinningState;
  initialState?: {
    columnVisibility?: VisibilityState;
  };
  onRefresh?: () => void;
}

const getCommonPinningStyles = <TData, TValue>(column: Column<TData, TValue>): CSSProperties => {
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
  bulkActions = [],
  columnPinning = { left: [], right: [] },
  initialState,
  enableSelection = true,
  onRefresh,
}: DataTableProps<TData, TValue>) {
  const { t } = useTranslation();

  let tableColumns = columns;

  // Add selection column
  if (enableSelection) {
    const selectColumnDef: ColumnDef<TData> = {
      id: 'tableSelection',
      header: ({ table }) => (
        <CheckboxCell
          checked={table.getIsAllPageRowsSelected()}
          onChange={(e) => table.toggleAllPageRowsSelected(!!e.target.checked)}
        ></CheckboxCell>
      ),
      cell: ({ row }) => (
        <CheckboxCell
          checked={row.getIsSelected()}
          onChange={(e) => row.toggleSelected(!!e.target.checked)}
        ></CheckboxCell>
      ),
      enableSorting: false,
      enableHiding: false,
      size: 40,
    };

    tableColumns = [selectColumnDef, ...columns];
  }

  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
    initialState?.columnVisibility || {},
  );

  const table = useReactTable({
    data,
    columns: tableColumns,
    pageCount,
    state: {
      sorting,
      globalFilter,
      pagination,
      rowSelection,
      columnPinning,
      columnVisibility,
    },
    enableRowSelection: enableSelection,
    enableColumnPinning: true,
    onRowSelectionChange,
    onPaginationChange,
    onSortingChange,
    onGlobalFilterChange,
    onColumnVisibilityChange: setColumnVisibility,
    columnResizeMode: 'onChange',
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    getCoreRowModel: getCoreRowModel(),
  });

  // TODO: Check if format is the intended
  const handleExportCSV = () => {
    const headers = table.getVisibleLeafColumns().map((column) => {
      return typeof column.columnDef.header === 'string' ? column.columnDef.header : column.id;
    });

    const rows = table.getRowModel().rows.map((row) =>
      row.getVisibleCells().map((cell) => {
        const value = cell.getValue();
        const stringValue = String(value ?? '').replace(/"/g, '""');
        return `"${stringValue}"`;
      }),
    );

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `export_${new Date().toISOString()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handlePrint = function () {
    window.print();
  };

  const selectedCount = Object.keys(rowSelection).length;

  const selectedRowsData = table.getSelectedRowModel().rows.map((row) => row.original);

  const nonPrintableColumns = ['tableSelection', 'tableActions'];

  return (
    <div className="flex flex-col pt-5 gap-y-3 data-table flex-1 min-h-0">
      <div className="flex justify-between data-table-header flex-none">
        <div className="flex items-center gap-3">
          <div className="text-gray-500 font-sans text-sm font-semibold leading-normal tracking-tight uppercase">
            {t('Table View')}
          </div>
          {selectedCount > 0 && bulkActions.length > 0 && (
            <DataTableBulkActions
              selectedCount={selectedCount}
              actions={bulkActions}
              onClearSelection={() => table.toggleAllPageRowsSelected(false)}
              selectedRows={selectedRowsData}
            />
          )}
        </div>

        <div className="ml-auto">
          <DataTableOptions
            table={table}
            onRefresh={onRefresh}
            onCSVExport={handleExportCSV}
            onPrint={handlePrint}
          />
        </div>
      </div>

      <div className="flex flex-col data-table-content bg-white overflow-hidden relative flex-1 min-h-0">
        {/* Loading Overlay */}
        {loading && (
          <div className="absolute inset-0 bg-white/60 z-50 flex items-center justify-center backdrop-blur-sm transition-all duration-200">
            <div className="flex flex-col items-center">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500 ">{t('Loading...')}</span>
            </div>
          </div>
        )}

        <div className="overflow-auto w-full printable-table-container flex-1">
          <table
            className="min-w-full border-separate border-spacing-0 table-fixed bg-white"
            style={{ width: table.getTotalSize() }}
          >
            <thead className="bg-gray-50 z-10 shadow-sm">
              {table.getHeaderGroups().map((headerGroup) => (
                <tr key={headerGroup.id}>
                  {headerGroup.headers.map((header) => {
                    const isSorted = header.column.getIsSorted();
                    const canSort = header.column.getCanSort();
                    return (
                      <th
                        key={header.id}
                        scope="col"
                        className={`relative ${nonPrintableColumns.includes(header.id) ? 'no-print' : ''}`}
                        style={{
                          ...getCommonPinningStyles(header.column),
                          position: 'sticky',
                          top: 0,
                          zIndex: header.column.getIsPinned() ? 30 : 10,
                          width: header.getSize(),
                          minWidth: header.column.columnDef.minSize,
                          maxWidth: header.column.columnDef.maxSize,
                        }}
                      >
                        <div className="px-3 py-2 h-8 flex uppercase bg-gray-50 border-b border-gray-200 text-sm items-center justify-between text-gray-500">
                          <div className="text-sm font-semibold ">
                            {flexRender(header.column.columnDef.header, header.getContext())}
                          </div>

                          {canSort && (
                            <div
                              className={`flex justify-center items-center p-1 size-6 ${header.column.getCanSort() ? 'cursor-pointer select-none' : ''}`}
                              onClick={header.column.getToggleSortingHandler()}
                            >
                              {isSorted === 'asc' ? (
                                <ChevronUp className="size-4" />
                              ) : isSorted === 'desc' ? (
                                <ChevronDown className="size-4" />
                              ) : (
                                <ChevronsUpDown className="size-4" />
                              )}
                            </div>
                          )}
                        </div>

                        <div
                          onMouseDown={header.getResizeHandler()}
                          onTouchStart={header.getResizeHandler()}
                          className={`absolute right-0 top-0 h-8 w-2 resizer cursor-col-resize ${header.column.getIsResizing() ? 'isResizing' : ''}`}
                        ></div>
                      </th>
                    );
                  })}
                </tr>
              ))}
            </thead>
            <tbody className="divide-y divide-gray-300">
              {table.getRowModel().rows.length > 0 ? (
                table.getRowModel().rows.map((row) => (
                  <tr key={row.id} className={row.getIsSelected() ? 'bg-gray-50' : ''}>
                    {row.getVisibleCells().map((cell) => (
                      <td
                        key={cell.id}
                        className={`px-3 py-2 bg-white border-b border-gray-200 ${nonPrintableColumns.includes(cell.column.id) ? 'no-print' : ''}`}
                        style={{
                          ...getCommonPinningStyles(cell.column),

                          width: cell.column.getSize(),
                          minWidth: cell.column.columnDef.minSize,
                          maxWidth: cell.column.columnDef.maxSize,
                        }}
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

      {/* TODO: Pending final design */}
      <div className="flex flex-col md:flex-row items-center data-table-footer">
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
