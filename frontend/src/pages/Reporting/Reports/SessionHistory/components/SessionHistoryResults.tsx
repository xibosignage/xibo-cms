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
import { Info, Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SessionHistoryLogType } from '../SessionHistoryConfig';

import Button from '@/components/ui/Button';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import { TextCell } from '@/components/ui/table/cells/TextCell';
import { sortRows } from '@/pages/Reporting/Reports/shared/utils/sortRows';
import type { SessionHistoryRow } from '@/services/sessionHistoryApi';

interface SessionHistoryResultsProps {
  rows: SessionHistoryRow[];
  logType: SessionHistoryLogType;
  isFetching: boolean;
  isError: boolean;
  onRefresh: () => void;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
}

function textCol<K extends keyof SessionHistoryRow>(
  key: K,
  header: string,
  size: number,
  truncate = false,
): ColumnDef<SessionHistoryRow> {
  return {
    accessorKey: key,
    header,
    size,
    cell: ({ row }) => <TextCell truncate={truncate}>{String(row.original[key] ?? '')}</TextCell>,
  };
}

function getColumns(
  t: TFunction,
  logType: SessionHistoryLogType,
  onViewDetails: (row: SessionHistoryRow) => void,
): ColumnDef<SessionHistoryRow>[] {
  if (logType === 'audit') {
    return [
      textCol('logDate', t('Date'), 160),
      textCol('userName', t('User Name'), 120),
      textCol('userId', t('User ID'), 90),
      textCol('ipAddress', t('IP Address'), 130),
      textCol('sessionHistoryId', t('Session ID'), 120),
      textCol('entity', t('Entity'), 130),
      textCol('entityId', t('Entity ID'), 100),
      textCol('message', t('Message'), 250, true),
      {
        id: 'details',
        header: t('Details'),
        size: 70,
        enableSorting: false,
        cell: ({ row }) => (
          <button
            className="cursor-pointer text-blue-600 hover:text-blue-800 p-1"
            onClick={() => onViewDetails(row.original)}
            title={t('View Details')}
          >
            <Info className="w-4 h-4" />
          </button>
        ),
      },
    ];
  }

  if (logType === 'debug') {
    return [
      textCol('logDate', t('Date'), 160),
      textCol('userName', t('UserName'), 120),
      textCol('userId', t('User ID'), 90),
      textCol('ipAddress', t('IP Address'), 130),
      textCol('sessionHistoryId', t('Session ID'), 120),
      textCol('channel', t('Channel'), 110),
      textCol('function', t('Function'), 150, true),
      textCol('type', t('Level'), 90),
      textCol('page', t('Page'), 150, true),
      textCol('message', t('Details'), 300, true),
    ];
  }

  // sessions (default)
  return [
    textCol('startTime', t('Start Date'), 160),
    textCol('lastUsedTime', t('End Date'), 160),
    textCol('duration', t('Duration'), 110),
    textCol('userName', t('UserName'), 140),
    textCol('userType', t('User Type'), 110),
    textCol('ipAddress', t('IP Address'), 130),
    textCol('sessionId', t('Session ID'), 120),
    textCol('userAgent', t('Browser'), 250, true),
  ];
}

function parseObjectAfter(objectAfter: unknown): Record<string, string> {
  if (!objectAfter) return {};
  if (typeof objectAfter === 'object' && !Array.isArray(objectAfter)) {
    const result: Record<string, string> = {};
    for (const [key, value] of Object.entries(objectAfter as Record<string, unknown>)) {
      result[key] = typeof value === 'object' ? JSON.stringify(value) : String(value ?? '');
    }
    return result;
  }
  if (typeof objectAfter === 'string') {
    try {
      return parseObjectAfter(JSON.parse(objectAfter));
    } catch {
      return {};
    }
  }
  return {};
}

export default function SessionHistoryResults({
  rows,
  logType,
  isFetching,
  isError,
  onRefresh,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
}: SessionHistoryResultsProps) {
  const { t } = useTranslation();
  const [detailsRow, setDetailsRow] = useState<SessionHistoryRow | null>(null);

  const columns = getColumns(t, logType, setDetailsRow);
  const showError = isError && !isFetching;
  const isEmpty = !isError && !isFetching && rows.length === 0;

  const sortedRows = sortRows(rows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const pageRows = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pagination.pageSize));

  const detailEntries = detailsRow ? parseObjectAfter(detailsRow.objectAfter) : {};

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
            getRowId={(row, index) => `${row.logId ?? row.sessionId}-${index}`}
          />
        )}
      </div>

      <Modal
        isOpen={detailsRow !== null}
        onClose={() => setDetailsRow(null)}
        title={t('Details')}
        size="md"
        showCloseButton
      >
        <div className="flex flex-col gap-4 p-8 pt-0">
          {Object.keys(detailEntries).length > 0 ? (
            <table className="w-full border-collapse text-sm">
              <thead>
                <tr className="border-b border-gray-200">
                  <th className="text-left py-2 px-3 font-semibold text-gray-700">
                    {t('Property')}
                  </th>
                  <th className="text-left py-2 px-3 font-semibold text-gray-700">{t('Value')}</th>
                </tr>
              </thead>
              <tbody>
                {Object.entries(detailEntries).map(([key, value]) => (
                  <tr key={key} className="border-b border-gray-100">
                    <td className="py-2 px-3 text-gray-600">{key}</td>
                    <td className="py-2 px-3 text-gray-800 break-all">{value}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="text-gray-400 text-sm py-4 text-center">{t('No details available.')}</p>
          )}
        </div>
      </Modal>
    </div>
  );
}
