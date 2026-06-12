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

import type { ColumnDef, OnChangeFn, PaginationState, SortingState } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { List, Loader2, PieChart as PieChartIcon, RefreshCw, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { CHART_PALETTE } from '../LibraryUsageConfig';

import type { PieChartItem } from './LibraryUsagePieChart';
import LibraryUsagePieChart from './LibraryUsagePieChart';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { getToggleButtonStyle } from '@/components/ui/table/DataTableOptions';
import type { ViewMode } from '@/components/ui/table/types';
import type { LibraryUsageChartData, LibraryUsageTableRow } from '@/services/libraryUsageApi';

interface LibraryUsageResultsProps {
  rows: LibraryUsageTableRow[];
  chart?: LibraryUsageChartData;
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

const getColumns = (t: TFunction): ColumnDef<LibraryUsageTableRow>[] => [
  { accessorKey: 'userId', header: t('ID'), size: 100 },
  { accessorKey: 'userName', header: t('User'), size: 240 },
  { accessorKey: 'bytesUsedFormatted', header: t('Usage'), size: 160 },
  { accessorKey: 'numFiles', header: t('Count Files'), size: 140 },
];

function sortRows(rows: LibraryUsageTableRow[], sorting: SortingState): LibraryUsageTableRow[] {
  const sort = sorting[0];
  if (!sort) {
    return rows;
  }
  // `bytesUsedFormatted` is a display string; sort by the raw byte count behind it.
  const key = (
    sort.id === 'bytesUsedFormatted' ? 'bytesUsed' : sort.id
  ) as keyof LibraryUsageTableRow;
  const sorted = [...rows].sort((a, b) => {
    const av = a[key];
    const bv = b[key];
    if (typeof av === 'number' && typeof bv === 'number') {
      return av - bv;
    }
    return String(av).localeCompare(String(bv));
  });
  return sort.desc ? sorted.reverse() : sorted;
}

/** Transform the Chart.js-shaped pie payload into items the recharts pie understands. */
function toPieItems(
  labels: string[] | undefined,
  values: number[] | undefined,
  formatDisplay?: (value: number) => string,
): PieChartItem[] {
  if (!labels || !values) {
    return [];
  }
  return labels.map((name, i) => {
    const value = values[i] ?? 0;
    return {
      name,
      value,
      color: CHART_PALETTE[i % CHART_PALETTE.length] ?? '#9ca3af',
      display: formatDisplay ? formatDisplay(value) : undefined,
    };
  });
}

export default function LibraryUsageResults({
  rows,
  chart,
  isFetching,
  isError,
  onRefresh,
  viewMode,
  onViewModeChange,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
}: LibraryUsageResultsProps) {
  const { t } = useTranslation();

  const columns = getColumns(t);
  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;
  const isTableMode = viewMode !== 'chart';

  const sortedRows = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  const libraryItems = toPieItems(
    chart?.Library_Usage.data.labels,
    chart?.Library_Usage.data.datasets[0]?.data,
  );
  const userItems = toPieItems(
    chart?.User_Percentage_Usage.data.labels,
    chart?.User_Percentage_Usage.data.datasets[0]?.data,
    (value) => `${value.toFixed(1)}%`,
  );

  return (
    <div
      className={`flex bg-slate-50 rounded-lg p-5 flex-col flex-1 mt-4 border border-slate-200 ${
        isTableMode ? 'min-h-0' : ''
      }`}
    >
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
              <PieChartIcon className="size-4" />
            </Button>
          </div>
        </div>
      </div>

      <div className={`relative flex flex-col flex-1 ${isTableMode ? 'min-h-0' : ''}`}>
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
            getRowId={(row) => String(row.userId)}
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <LibraryUsagePieChart
              title={t('Library Usage')}
              data={libraryItems}
              emptyLabel={t('No library data available.')}
            />
            <LibraryUsagePieChart
              title={t('User Percentage Usage')}
              data={userItems}
              emptyLabel={t('No user data available.')}
            />
          </div>
        )}
      </div>
    </div>
  );
}
