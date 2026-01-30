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
  type PaginationState,
  type RowSelectionState,
  type OnChangeFn,
  type Row,
} from '@tanstack/react-table';
import { Loader2, FileSearch2 } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { DataTableBulkActions, type DataTableBulkAction } from './DataTableBulkActions';
import { DataTableOptions } from './DataTableOptions';
import { DataTablePagination } from './DataTablePagination';

interface DataGridProps<TData> {
  data: TData[];
  pageCount: number;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  rowSelection: RowSelectionState;
  onRowSelectionChange: OnChangeFn<RowSelectionState>;
  loading?: boolean;
  onRefresh?: () => void;
  viewMode: 'grid';
  onViewModeChange: (mode: 'table' | 'grid') => void;
  renderCard: (
    item: TData,
    isSelected: boolean,
    toggleSelect: (val: boolean) => void,
  ) => React.ReactNode;
  bulkActions?: DataTableBulkAction<TData>[];
  getRowId?: (originalRow: TData, index: number, parent?: Row<TData>) => string;
}

export function DataGrid<TData>({
  data,
  pageCount,
  pagination,
  onPaginationChange,
  rowSelection,
  onRowSelectionChange,
  loading = false,
  onRefresh,
  viewMode,
  onViewModeChange,
  renderCard,
  bulkActions = [],
  getRowId,
}: DataGridProps<TData>) {
  const { t } = useTranslation();
  const [showLoading, setShowLoading] = useState(false);

  const table = useReactTable({
    data,
    columns: [],
    pageCount,
    state: { pagination, rowSelection },
    enableRowSelection: true,
    onRowSelectionChange,
    onPaginationChange,
    manualPagination: true,
    getCoreRowModel: getCoreRowModel(),
    getRowId: getRowId,
  });

  const selectedCount = Object.keys(rowSelection).length;
  const selectedRowsData = table.getSelectedRowModel().rows.map((row) => row.original);

  useEffect(() => {
    let timer: NodeJS.Timeout;
    if (loading) {
      timer = setTimeout(() => setShowLoading(true), 150);
    } else {
      setShowLoading(false);
    }
    return () => clearTimeout(timer);
  }, [loading]);

  return (
    <div className="flex flex-col pt-5 gap-y-3 flex-1 min-h-0">
      <div className="flex justify-between data-table-header flex-none">
        <div className="flex items-center gap-3">
          <div className="text-gray-500 font-sans text-sm font-semibold leading-normal tracking-tight uppercase">
            {t('Gallery View')}
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
            viewMode={viewMode}
            onViewModeChange={onViewModeChange}
          />
        </div>
      </div>

      <div className="flex flex-col bg-white overflow-hidden relative flex-1 min-h-0">
        {showLoading && (
          <div className="absolute inset-0 bg-white/60 z-50 flex items-center justify-center backdrop-blur-sm">
            <div className="flex flex-col items-center">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="mt-2 text-gray-500">{t('Loading...')}</span>
            </div>
          </div>
        )}

        {table.getRowModel().rows.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 overflow-y-auto">
            {table.getRowModel().rows.map((row) => (
              <div key={row.id}>
                {renderCard(row.original, row.getIsSelected(), (checked) =>
                  row.toggleSelected(checked),
                )}
              </div>
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center flex-1 h-full gap-3">
            <div className="inline-flex justify-center items-center size-15.5 rounded-full bg-gray-100 text-gray-500 border-7 border-gray-50">
              <FileSearch2 className="shrink-0 size-5" />
            </div>

            <h3 className="text-lg font-semibold text-gray-800">{t('No results found.')}</h3>

            <p className="text-gray-500">
              {t("Reset your filters or adjust your search to find what you're looking for.")}
            </p>
          </div>
        )}
      </div>

      <DataTablePagination table={table} loading={loading} />
    </div>
  );
}
