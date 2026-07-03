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

import { type ColumnDef, type PaginationState, type SortingState } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  SUMMARY_GROUP_OPTIONS,
  aggregateSummary,
  type SspSummaryGroupBy,
  type SspSummaryRow,
} from '../SspActivityConfig';

import SspActivityChart from './SspActivityChart';

import Button from '@/components/ui/Button';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import { DataTable } from '@/components/ui/table/DataTable';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { sortRows } from '@/pages/Reporting/Reports/shared/utils/sortRows';
import type { SspActivityRow } from '@/services/sspActivityApi';
import type { DateLike } from '@/utils/date';

type DateFormatter = (value: DateLike) => string;
type BoolRenderer = (value: boolean) => string;

const REPORT_PAGE_SIZE = 10;
const SUMMARY_ROW_PX = 44;
const SUMMARY_TABLE_CHROME_PX = 132;

const getDetailedColumns = (
  t: TFunction,
  fmtDt: DateFormatter,
  yesNo: BoolRenderer,
): ColumnDef<SspActivityRow>[] => [
  {
    accessorKey: 'scheduledAt',
    header: t('Scheduled At'),
    cell: (c) => fmtDt(c.getValue<string | null>()),
    size: 200,
  },
  { accessorKey: 'campaignId', header: t('Campaign'), size: 140 },
  { accessorKey: 'displayId', header: t('Display ID'), size: 110 },
  {
    accessorKey: 'isPlayed',
    header: t('Played?'),
    size: 100,
    cell: (c) => yesNo(c.getValue<boolean>()),
  },
  {
    accessorKey: 'isErrored',
    header: t('Errored?'),
    size: 100,
    cell: (c) => yesNo(c.getValue<boolean>()),
  },
  { accessorKey: 'impressions', header: t('Impressions'), size: 120 },
  {
    accessorKey: 'impressionDate',
    header: t('Impression Date'),
    cell: (c) => fmtDt(c.getValue<string | null>()),
    size: 200,
  },
  { accessorKey: 'impressionActual', header: t('Impression Actual'), size: 200 },
  { accessorKey: 'errors', header: t('Errors'), size: 90 },
  {
    accessorKey: 'errorDate',
    header: t('Error Date'),
    cell: (c) => fmtDt(c.getValue<string | null>()),
    size: 200,
  },
  { accessorKey: 'errorCode', header: t('Error Code'), size: 140 },
];

const getSummaryColumns = (
  t: TFunction,
  groupBy: SspSummaryGroupBy,
): ColumnDef<SspSummaryRow>[] => {
  const columns: ColumnDef<SspSummaryRow>[] = [];

  if (groupBy !== 'errorCode') {
    columns.push({ accessorKey: 'date', header: t('Date'), size: 120 });
    columns.push({ accessorKey: 'time', header: t('Hour'), size: 90 });
  }

  columns.push(
    { accessorKey: 'campaignId', header: t('Campaign'), size: 140 },
    { accessorKey: 'playCount', header: t('Play Count'), size: 120 },
    { accessorKey: 'errorCount', header: t('Error Count'), size: 120 },
    { accessorKey: 'missesCount', header: t('Misses Count'), size: 120 },
    { accessorKey: 'impressions', header: t('Impressions'), size: 120 },
    { accessorKey: 'impressionActual', header: t('Impression Actual'), size: 200 },
  );

  if (groupBy !== 'hour') {
    columns.push({ accessorKey: 'errorCode', header: t('Error Code'), size: 140 });
  }

  return columns;
};

function ReportDataTable<T>({
  columns,
  rows,
  loading,
  getRowId,
}: {
  columns: ColumnDef<T>[];
  rows: T[];
  loading: boolean;
  getRowId: (row: T, index: number) => string;
}) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: REPORT_PAGE_SIZE,
  });
  const [sorting, setSorting] = useState<SortingState>([]);

  const sorted = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sorted.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  return (
    <DataTable
      columns={columns}
      data={pageRows}
      pageCount={pageCount}
      pagination={pagination}
      onPaginationChange={setPagination}
      sorting={sorting}
      onSortingChange={setSorting}
      globalFilter=""
      onGlobalFilterChange={() => {}}
      rowSelection={{}}
      onRowSelectionChange={() => {}}
      enableSelection={false}
      loading={loading}
      hideToolbar
      getRowId={getRowId}
    />
  );
}

type ResultTab = 'summary' | 'detailed';

interface SspActivityResultsProps {
  rows: SspActivityRow[];
  isFetching: boolean;
  isError: boolean;
  errorMessage?: string | null;
  onRefresh: () => void;
}

export default function SspActivityResults({
  rows,
  isFetching,
  isError,
  errorMessage,
  onRefresh,
}: SspActivityResultsProps) {
  const { t } = useTranslation();
  const { formatDateTime } = useDateFormatter();

  const [tab, setTab] = useState<ResultTab>('summary');
  const [groupBy, setGroupBy] = useState<SspSummaryGroupBy>('hour');

  const fmtDt: DateFormatter = (value) => (value ? formatDateTime(value) : '');
  const yesNo: BoolRenderer = (value) => (value ? t('Yes') : t('No'));

  const summary = aggregateSummary(rows, groupBy, fmtDt);

  const detailedColumns = getDetailedColumns(t, fmtDt, yesNo);
  const summaryColumns = getSummaryColumns(t, groupBy);

  const visibleSummaryRows = Math.min(summary.rows.length, REPORT_PAGE_SIZE) || 1;
  const summaryTableHeight = SUMMARY_TABLE_CHROME_PX + visibleSummaryRows * SUMMARY_ROW_PX;

  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;

  const tabButtonStyle = (isActive: boolean) =>
    `px-4 py-2 text-sm font-semibold border-b-2 -mb-px ${
      isActive
        ? 'border-xibo-blue-600 text-xibo-blue-600'
        : 'border-transparent text-gray-500 hover:text-gray-700'
    }`;

  return (
    <div className="flex bg-slate-50 rounded-lg p-5 flex-col flex-1 min-h-0 mt-4 border border-slate-200">
      <div className="flex items-center justify-between mb-3 flex-none">
        <div className="flex items-center border-b border-gray-200">
          <button
            type="button"
            className={tabButtonStyle(tab === 'summary')}
            onClick={() => setTab('summary')}
          >
            {t('Summary')}
          </button>
          <button
            type="button"
            className={tabButtonStyle(tab === 'detailed')}
            onClick={() => setTab('detailed')}
          >
            {t('Detailed')}
          </button>
        </div>

        <Button
          type="button"
          onClick={onRefresh}
          disabled={isFetching}
          variant="tertiary"
          leftIcon={RefreshCw}
        >
          {t('Refresh')}
        </Button>
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
              {errorMessage || t('Something went wrong generating this report. Please try again.')}
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
        ) : tab === 'summary' ? (
          <div className="flex flex-col gap-5 min-h-0 overflow-y-auto">
            <div className="max-w-xs flex-none">
              <SelectDropdown
                label={t('Filter Options')}
                value={groupBy}
                options={SUMMARY_GROUP_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
                onSelect={(val) => setGroupBy(val as SspSummaryGroupBy)}
              />
            </div>

            <div className="flex flex-col flex-none" style={{ height: summaryTableHeight }}>
              <ReportDataTable
                key={groupBy}
                columns={summaryColumns}
                rows={summary.rows}
                loading={isFetching}
                getRowId={(row, index) => `${row.key}-${index}`}
              />
            </div>

            <div className="flex flex-col flex-none">
              <h3 className="mb-2 text-sm font-semibold text-gray-800">{t('Summary Chart')}</h3>
              <SspActivityChart stats={summary.stats} />
            </div>
          </div>
        ) : (
          <ReportDataTable
            columns={detailedColumns}
            rows={rows}
            loading={isFetching}
            getRowId={(row, index) => `${row.displayId}-${row.scheduledAt}-${index}`}
          />
        )}
      </div>
    </div>
  );
}
