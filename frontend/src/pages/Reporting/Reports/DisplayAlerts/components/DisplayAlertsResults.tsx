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
import { Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { TextCell } from '@/components/ui/table/cells/TextCell';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { sortRows } from '@/pages/Reporting/Reports/shared/utils/sortRows';
import type { DisplayAlertsRow } from '@/services/displayAlertsApi';
import { formatDurationText } from '@/utils/formatters';

type Timestamp = string | number;

function toDate(value: Timestamp): Date | null {
  const seconds = Number(value);
  if (!value || !Number.isFinite(seconds)) return null;
  return new Date(seconds * 1000);
}

interface DisplayAlertsResultsProps {
  rows: DisplayAlertsRow[];
  isFetching: boolean;
  isError: boolean;
  onRefresh: () => void;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
}

function computeDuration(start: Timestamp, end: Timestamp, t: TFunction): string {
  const startDate = toDate(start);
  const endDate = toDate(end);
  if (!startDate || !endDate) {
    return '';
  }
  const diffSeconds = Math.max(0, Math.floor((endDate.getTime() - startDate.getTime()) / 1000));
  return formatDurationText(diffSeconds, t);
}

function getColumns(
  t: TFunction,
  formatTimestamp: (value: Timestamp) => string,
): ColumnDef<DisplayAlertsRow>[] {
  return [
    {
      accessorKey: 'displayId',
      header: t('Display ID'),
      size: 100,
      cell: ({ row }) => <TextCell>{String(row.original.displayId)}</TextCell>,
    },
    {
      accessorKey: 'display',
      header: t('Display'),
      size: 200,
      cell: ({ row }) => <TextCell>{row.original.display}</TextCell>,
    },
    {
      accessorKey: 'eventType',
      header: t('Event Type'),
      size: 150,
      cell: ({ row }) => <TextCell>{row.original.eventType}</TextCell>,
    },
    {
      accessorKey: 'start',
      header: t('Start'),
      size: 160,
      cell: ({ row }) => <TextCell>{formatTimestamp(row.original.start)}</TextCell>,
    },
    {
      accessorKey: 'end',
      header: t('End'),
      size: 160,
      cell: ({ row }) => <TextCell>{formatTimestamp(row.original.end)}</TextCell>,
    },
    {
      id: 'duration',
      header: t('Duration'),
      size: 180,
      cell: ({ row }) => (
        <TextCell>{computeDuration(row.original.start, row.original.end, t)}</TextCell>
      ),
    },
    {
      accessorKey: 'refId',
      header: t('Reference'),
      size: 100,
      cell: ({ row }) => <TextCell>{String(row.original.refId)}</TextCell>,
    },
    {
      accessorKey: 'detail',
      header: t('Detail'),
      size: 300,
      cell: ({ row }) => <TextCell truncate>{row.original.detail}</TextCell>,
    },
  ];
}

export default function DisplayAlertsResults({
  rows,
  isFetching,
  isError,
  onRefresh,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
}: DisplayAlertsResultsProps) {
  const { t } = useTranslation();
  const { formatDateTime } = useDateFormatter();

  const formatTimestamp = (value: Timestamp) => {
    const date = toDate(value);
    return date ? formatDateTime(date) : '';
  };

  const columns = getColumns(t, formatTimestamp);
  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;

  const sortedRows = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  return (
    <div className="flex bg-slate-50 rounded-lg p-5 flex-col flex-1 min-h-0 mt-4 border border-slate-200">
      <div className="flex items-center justify-end mb-3 flex-none">
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
        ) : (
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
            getRowId={(row, index) => `${row.displayId}-${row.eventTypeId}-${index}`}
          />
        )}
      </div>
    </div>
  );
}
