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

import type { ColumnDef, PaginationState, SortingState, OnChangeFn } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import {
  BarChart3,
  LineChart as LineChartIcon,
  List,
  Loader2,
  PieChart as PieChartIcon,
  RefreshCw,
  TriangleAlert,
  type LucideIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

import StatsChart from './StatsChart';
import type { StatsChartType, StatsSelectOption } from './types';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { getToggleButtonStyle } from '@/components/ui/table/DataTableOptions';
import type { ViewMode } from '@/components/ui/table/types';
import type { StatsReportTableRow } from '@/services/statsReportApi';

const CHART_ICONS: Record<StatsChartType, LucideIcon> = {
  line: LineChartIcon,
  bar: BarChart3,
  pie: PieChartIcon,
};

interface StatsReportResultsProps {
  rows: StatsReportTableRow[];
  metadata?: { periodStart: string; periodEnd: string; type: string; subject: string };
  typeOptions: StatsSelectOption[];
  chartType: StatsChartType;
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

const getColumns = (t: TFunction): ColumnDef<StatsReportTableRow>[] => [
  { accessorKey: 'label', header: t('Period'), size: 200 },
  { accessorKey: 'duration', header: t('Duration (s)'), size: 160 },
  { accessorKey: 'count', header: t('Count'), size: 120 },
];

function sortRows(rows: StatsReportTableRow[], sorting: SortingState): StatsReportTableRow[] {
  const sort = sorting[0];
  if (!sort) {
    return rows;
  }
  const key = sort.id as keyof StatsReportTableRow;
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

export default function StatsReportResults({
  rows,
  metadata,
  typeOptions,
  chartType,
  isFetching,
  isError,
  onRefresh,
  viewMode,
  onViewModeChange,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
}: StatsReportResultsProps) {
  const { t } = useTranslation();

  const columns = getColumns(t);
  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;
  const isTableMode = viewMode !== 'chart';
  const ChartIcon = CHART_ICONS[chartType];

  const sortedRows = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  return (
    <div className="flex bg-slate-50 rounded-lg p-5 flex-col flex-1 min-h-0 mt-4 border border-slate-200">
      <div className="flex items-center justify-between mb-3 flex-none">
        <div className="flex items-center gap-4 text-sm text-gray-500 font-semibold">
          {metadata?.subject && (
            <span>
              {t(typeOptions.find((o) => o.value === metadata.type)?.label ?? metadata.type)}:
              <strong className="ml-3 text-gray-800">{metadata.subject}</strong>
            </span>
          )}
        </div>

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
              <ChartIcon className="size-4" />
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
            getRowId={(row, index) => `${row.label}-${index}`}
          />
        ) : (
          <StatsChart rows={rows} type={chartType} />
        )}
      </div>
    </div>
  );
}
