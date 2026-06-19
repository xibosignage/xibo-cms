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

import type {
  ColumnDef,
  OnChangeFn,
  PaginationState,
  SortingState,
  VisibilityState,
} from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { TextCell } from '@/components/ui/table/cells/TextCell';
import { sortRows } from '@/pages/Reporting/Reports/shared/utils/sortRows';
import type { ProofOfPlayRow } from '@/services/proofOfPlayApi';
import { formatDurationText } from '@/utils/formatters';

interface ProofOfPlayResultsProps {
  rows: ProofOfPlayRow[];
  isFetching: boolean;
  isError: boolean;
  onRefresh: () => void;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
  columnVisibility: VisibilityState;
  onColumnVisibilityChange: OnChangeFn<VisibilityState>;
}

function capitalize(value: string): string {
  if (!value) return '';
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function getColumns(t: TFunction): ColumnDef<ProofOfPlayRow>[] {
  return [
    {
      accessorKey: 'type',
      header: t('Type'),
      size: 90,
      cell: ({ row }) => <TextCell>{capitalize(row.original.type)}</TextCell>,
    },
    { accessorKey: 'displayId', header: t('Display ID'), size: 100 },
    { accessorKey: 'display', header: t('Display'), size: 160 },
    { accessorKey: 'displayGroupId', header: t('Display Group ID'), size: 140 },
    { accessorKey: 'displayGroup', header: t('Display Group'), size: 150 },
    { accessorKey: 'tagId', header: t('Tag ID'), size: 80 },
    { accessorKey: 'tagName', header: t('Tag Name'), size: 120 },
    { accessorKey: 'parentCampaign', header: t('Campaign'), size: 150 },
    { accessorKey: 'layoutId', header: t('Layout ID'), size: 100 },
    { accessorKey: 'layout', header: t('Layout'), size: 160 },
    { accessorKey: 'widgetId', header: t('Widget ID'), size: 100 },
    { accessorKey: 'media', header: t('Media'), size: 180 },
    { accessorKey: 'tag', header: t('Tag'), size: 110 },
    {
      accessorKey: 'numberPlays',
      header: t('Number of Plays'),
      size: 130,
      cell: ({ row }) => <TextCell>{row.original.numberPlays.toLocaleString()}</TextCell>,
    },
    {
      id: 'durationFormatted',
      accessorKey: 'duration',
      header: t('Total Duration'),
      size: 180,
      cell: ({ row }) => <TextCell>{formatDurationText(row.original.duration, t)}</TextCell>,
    },
    {
      id: 'durationSeconds',
      accessorKey: 'duration',
      header: t('Total Duration (s)'),
      size: 140,
      cell: ({ row }) => <TextCell>{String(row.original.duration)}</TextCell>,
    },
    { accessorKey: 'minStart', header: t('First Period Shown'), size: 160 },
    { accessorKey: 'maxEnd', header: t('Last Period Shown'), size: 160 },
  ];
}

export default function ProofOfPlayResults({
  rows,
  isFetching,
  isError,
  onRefresh,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
  columnVisibility,
  onColumnVisibilityChange,
}: ProofOfPlayResultsProps) {
  const { t } = useTranslation();

  const columns = getColumns(t);
  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;

  const sortedRows = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  return (
    <div className="flex bg-slate-50 rounded-lg p-5 flex-col flex-1 min-h-0 mt-4 border border-slate-200">
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
            onRefresh={onRefresh}
            columnVisibility={columnVisibility}
            onColumnVisibilityChange={onColumnVisibilityChange}
            getRowId={(row, index) => `${row.displayId}-${row.layoutId}-${row.widgetId}-${index}`}
          />
        )}
      </div>
    </div>
  );
}
