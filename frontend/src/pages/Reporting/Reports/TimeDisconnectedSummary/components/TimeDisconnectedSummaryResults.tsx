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
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
  type ColumnDef,
  type OnChangeFn,
  type PaginationState,
  type SortingState,
} from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { BarChart3, List, Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { GroupBy } from '../TimeDisconnectedSummaryConfig';

import TimeDisconnectedSummaryChart from './TimeDisconnectedSummaryChart';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { getToggleButtonStyle } from '@/components/ui/table/DataTableOptions';
import { DataTablePagination } from '@/components/ui/table/DataTablePagination';
import type { ViewMode } from '@/components/ui/table/types';
import { sortRows } from '@/pages/Reporting/Reports/shared/utils/sortRows';
import type { TimeDisconnectedSummaryRow } from '@/services/timeDisconnectedSummaryApi';

const EMPTY_COLUMNS: ColumnDef<TimeDisconnectedSummaryRow>[] = [];

interface TimeDisconnectedSummaryResultsProps {
  rows: TimeDisconnectedSummaryRow[];
  groupBy: GroupBy;
  isFetching: boolean;
  isError: boolean;
  onRefresh: () => void;
  viewMode: ViewMode;
  onViewModeChange: (mode: ViewMode) => void;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
}

function getColumns(t: TFunction, groupBy: GroupBy): ColumnDef<TimeDisconnectedSummaryRow>[] {
  if (groupBy === 'displayGroup') {
    return [
      { accessorKey: 'displayGroupId', header: t('Display Group ID'), size: 120 },
      { accessorKey: 'displayGroup', header: t('Display Group'), size: 220 },
      { accessorKey: 'timeDisconnected', header: t('Time Disconnected'), size: 150 },
      { accessorKey: 'timeConnected', header: t('Time Connected'), size: 150 },
      { accessorKey: 'avgTimeDisconnected', header: t('Average Time Disconnected'), size: 170 },
      { accessorKey: 'avgTimeConnected', header: t('Average Time Connected'), size: 170 },
      { accessorKey: 'availabilityPercentage', header: t('Availability'), size: 130 },
      { accessorKey: 'postUnits', header: t('Units'), size: 110 },
    ];
  }
  return [
    { accessorKey: 'displayId', header: t('Display ID'), size: 110 },
    { accessorKey: 'display', header: t('Display'), size: 240 },
    { accessorKey: 'timeDisconnected', header: t('Time Disconnected'), size: 150 },
    { accessorKey: 'timeConnected', header: t('Time Connected'), size: 150 },
    { accessorKey: 'availabilityPercentage', header: t('Availability'), size: 130 },
    { accessorKey: 'postUnits', header: t('Units'), size: 110 },
  ];
}

export default function TimeDisconnectedSummaryResults({
  rows,
  groupBy,
  isFetching,
  isError,
  onRefresh,
  viewMode,
  onViewModeChange,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
}: TimeDisconnectedSummaryResultsProps) {
  const { t } = useTranslation();

  const columns = getColumns(t, groupBy);
  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;
  const isTableMode = viewMode !== 'chart';

  const sortedRows = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  const chartTable = useReactTable({
    data: sortedRows,
    columns: EMPTY_COLUMNS,
    state: { pagination },
    onPaginationChange,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    autoResetPageIndex: false,
  });

  return (
    <div className="flex bg-slate-50 rounded-lg p-5 flex-col flex-1 min-h-0 mt-4 border border-slate-200">
      <div className="flex items-center justify-end mb-3 flex-none">
        <div className="flex items-center gap-3">
          <Button
            type="button"
            onClick={onRefresh}
            disabled={isFetching}
            variant="tertiary"
            leftIcon={RefreshCw}
          >
            {t('Refresh')}
          </Button>

          <div className="flex items-center rounded-lg bg-gray-50">
            <Button
              variant="tertiary"
              onClick={() => onViewModeChange('table')}
              className={getToggleButtonStyle(isTableMode)}
              title={t('Table View')}
            >
              <List className="size-4" />
            </Button>
            <Button
              variant="tertiary"
              onClick={() => onViewModeChange('chart')}
              className={getToggleButtonStyle(!isTableMode)}
              title={t('Chart View')}
            >
              <BarChart3 className="size-4" />
            </Button>
          </div>
        </div>
      </div>

      <div className="flex-1 min-h-0 relative flex flex-col">
        {isFetching && (
          <div className="absolute inset-0 bg-white/60 z-10 flex items-center justify-center backdrop-blur-sm">
            <div className="flex flex-col items-center gap-2">
              <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
              <span className="text-gray-500 text-sm">{t('Loading...')}</span>
            </div>
          </div>
        )}

        {showError ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-3 bg-red-50 rounded-lg border border-dashed border-red-200">
            <TriangleAlert className="w-8 h-8 text-red-400" />
            <p className="text-red-600 text-sm">
              {t('Something went wrong generating this report. Please try again.')}
            </p>
            <Button variant="tertiary" onClick={onRefresh} leftIcon={RefreshCw}>
              {t('Retry')}
            </Button>
          </div>
        ) : isEmpty ? (
          <div className="flex-1 flex items-center justify-center bg-gray-50 rounded-lg border border-dashed border-gray-200">
            <p className="text-gray-400 text-sm">
              {t('No results found. Adjust your filters and click Apply.')}
            </p>
          </div>
        ) : isTableMode ? (
          <DataTable
            columns={columns}
            data={pageRows}
            pageCount={pageCount}
            rowCount={rows.length}
            pagination={pagination}
            onPaginationChange={onPaginationChange}
            sorting={sorting}
            onSortingChange={onSortingChange}
            globalFilter=""
            onGlobalFilterChange={() => {}}
            rowSelection={{}}
            onRowSelectionChange={() => {}}
            enableSelection={false}
            loading={isFetching}
            hideToolbar
            getRowId={(row, index) => `${row.displayId}-${row.displayGroupId}-${index}`}
          />
        ) : (
          <div className="flex flex-col flex-1 min-h-0">
            <TimeDisconnectedSummaryChart
              rows={chartTable.getRowModel().rows.map((r) => r.original)}
              groupBy={groupBy}
            />
            <div className="flex-none">
              <DataTablePagination
                table={chartTable}
                pagination={pagination}
                pageCount={chartTable.getPageCount()}
                loading={isFetching}
              />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
