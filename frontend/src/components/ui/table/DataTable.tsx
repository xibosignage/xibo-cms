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

import {
  useReactTable,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
  type SortingState,
  type PaginationState,
  type OnChangeFn,
  type Row,
  type RowSelectionState,
  type ColumnPinningState,
  type Column,
  type VisibilityState,
} from '@tanstack/react-table';
import { Loader2, ChevronUp, ChevronDown, ChevronsUpDown, FileSearch2 } from 'lucide-react';
import { type CSSProperties, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { DataTableBulkAction } from './DataTableBulkActions';
import { DataTableBulkActions } from './DataTableBulkActions';
import { DataTableOptions } from './DataTableOptions';
import { DataTablePagination } from './DataTablePagination';

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
  viewMode?: 'table' | 'grid';
  onViewModeChange?: (mode: 'table' | 'grid') => void;
  getRowId?: (originalRow: TData, index: number, parent?: Row<TData>) => string;
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
  viewMode,
  onViewModeChange,
  getRowId,
}: DataTableProps<TData, TValue>) {
  const { t } = useTranslation();

  const [showLoading, setShowLoading] = useState(false);

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
      enableResizing: false,
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
    getRowId: getRowId,
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

  // Prevent loading to show if request takes less than X seconds
  useEffect(() => {
    let timer: NodeJS.Timeout;

    if (loading) {
      timer = setTimeout(() => {
        setShowLoading(true);
      }, 150);
    } else {
      setShowLoading(false);
    }
    return () => clearTimeout(timer);
  }, [loading]);

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
            columnVisibility={columnVisibility}
            viewMode={viewMode}
            onViewModeChange={onViewModeChange}
          />
        </div>
      </div>

      <div className="flex flex-col data-table-content bg-white overflow-hidden relative flex-1 min-h-0">
        {/* Loading Overlay */}
        {showLoading && (
          <div className="absolute inset-0 bg-white/60 z-50 flex items-center justify-center backdrop-blur-sm animate-in fade-in duration-300">
            <div className="flex flex-col items-center">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500">{t('Loading...')}</span>
            </div>
          </div>
        )}

        <div className="overflow-auto w-full printable-table-container flex-1">
          <table
            className={twMerge(
              'border-separate border-spacing-0 bg-white',
              'w-full min-w-full',
              // Fix for header width with no results
              table.getRowModel().rows.length === 0
                ? 'table-auto min-h-full'
                : 'table-fixed h-auto',
            )}
            style={{
              minWidth: table.getRowModel().rows.length === 0 ? '100%' : table.getTotalSize(),
            }}
          >
            <thead className="z-10 shadow-sm">
              {table.getHeaderGroups().map((headerGroup) => (
                <tr className="bg-gray-50 " key={headerGroup.id}>
                  {headerGroup.headers.map((header) => {
                    const isSorted = header.column.getIsSorted();
                    const canSort = header.column.getCanSort();
                    const canResize = header.column.getCanResize();

                    const headerContent = header.column.columnDef.header;
                    const titleText = typeof headerContent === 'string' ? headerContent : header.id;

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
                          <div
                            className="text-sm font-semibold text-nowrap overflow-hidden"
                            title={titleText}
                          >
                            {flexRender(header.column.columnDef.header, header.getContext())}
                          </div>

                          {canSort && (
                            <div
                              className={twMerge(
                                'flex justify-center items-center p-1 size-6',
                                header.column.getCanSort() ? 'cursor-pointer select-none' : '',
                              )}
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

                        {canResize && (
                          <div
                            onMouseDown={header.getResizeHandler()}
                            onTouchStart={header.getResizeHandler()}
                            className={twMerge(
                              'absolute right-0 top-0 h-8 w-2 resizer cursor-col-resize',
                              header.column.getIsResizing() ? 'isResizing' : '',
                            )}
                          ></div>
                        )}
                      </th>
                    );
                  })}
                </tr>
              ))}
            </thead>
            <tbody className="divide-y divide-gray-300">
              {table.getRowModel().rows.length > 0 ? (
                table.getRowModel().rows.map((row) => {
                  const isSelected = row.getIsSelected();
                  const rowBackgroundColor = isSelected ? 'bg-blue-50' : 'bg-white';

                  return (
                    <tr key={row.id} className={rowBackgroundColor}>
                      {row.getVisibleCells().map((cell) => (
                        <td
                          key={cell.id}
                          className={twMerge(
                            'px-3 py-2 border-b border-gray-200',
                            rowBackgroundColor,
                            nonPrintableColumns.includes(cell.column.id) ? 'no-print' : '',
                          )}
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
                  );
                })
              ) : (
                <tr>
                  <td colSpan={columns.length} className="text-center text-gray-500 no-results">
                    {!loading && (
                      <div className="flex flex-col items-center justify-center gap-3">
                        <div className="inline-flex justify-center items-center size-15.5 rounded-full bg-gray-100 text-gray-500 border-7 border-gray-50">
                          <FileSearch2 className="shrink-0 size-5" />
                        </div>

                        <h3 className="text-lg font-semibold text-gray-800">
                          {t('No results found.')}
                        </h3>

                        <p className="text-gray-500">
                          {t(
                            "Reset your filters or adjust your search to find what you're looking for.",
                          )}
                        </p>
                      </div>
                    )}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <DataTablePagination table={table} loading={loading} pageSizeOptions={pageSizeOptions} />
    </div>
  );
}
