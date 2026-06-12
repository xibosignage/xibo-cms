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

import type { Table } from '@tanstack/react-table';
import { Loader2, RefreshCw, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { DisplayReportRow } from '../TimeConnectedConfig';

import TimeConnectedRow from './TimeConnectedRow';

import Button from '@/components/ui/Button';
import { DataTablePagination } from '@/components/ui/table/DataTablePagination';

interface TimeConnectedResultsProps {
  table: Table<DisplayReportRow>;
  totalRowCount: number;
  metadata?: { periodStart: string; periodEnd: string };
  isFetching: boolean;
  isError: boolean;
  onRefresh: () => void;
}

export default function TimeConnectedResults({
  table,
  totalRowCount,
  metadata,
  isFetching,
  isError,
  onRefresh,
}: TimeConnectedResultsProps) {
  const { t } = useTranslation();

  const rangeStart = metadata?.periodStart.split(' ')[0];
  const rangeEnd = metadata?.periodEnd.split(' ')[0];

  const pageRows = table.getRowModel().rows;

  return (
    <div className="flex p-5 flex-col flex-1 min-h-125">
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

      <div className="flex items-start gap-3 mb-3 text-xs text-gray-500">
        <div className="flex items-center gap-1.5">
          <div className="w-4 h-3 rounded-sm bg-teal-500" />
          <span>{t('Connected')}</span>
        </div>
        <div className="flex items-center gap-1.5">
          <div className="w-4 h-3 rounded-sm bg-gray-200" />
          <span>{t('Disconnected')}</span>
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

        {isError && !isFetching ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-3 bg-red-50 rounded-lg border border-dashed border-red-200">
            <TriangleAlert className="w-8 h-8 text-red-400" />
            <p className="text-red-600 text-sm">
              {t('Something went wrong generating this report. Please try again.')}
            </p>
            <Button variant="tertiary" onClick={onRefresh} leftIcon={RefreshCw}>
              {t('Retry')}
            </Button>
          </div>
        ) : !isFetching && totalRowCount === 0 ? (
          <div className="flex-1 flex items-center justify-center bg-gray-50 rounded-lg border border-dashed border-gray-200">
            <p className="text-gray-400 text-sm">
              {t('No results found. Adjust your filters and click Apply.')}
            </p>
          </div>
        ) : (
          <div className="flex flex-col flex-1 min-h-0 overflow-hidden bg-white">
            <div className="flex-1 min-h-0 overflow-y-auto">
              <div className="sticky top-0 z-20 flex items-center gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <div className="flex-none w-6" />
                <div className="flex-none w-45">{t('Display')}</div>
                <div className="flex-none w-16 text-right">{t('Uptime')}</div>
                <div className="flex-1">
                  {t('Timeline')}
                  {rangeStart && rangeEnd && (
                    <span className="ml-1 normal-case font-normal text-gray-400">
                      ({rangeStart} &rarr; {rangeEnd})
                    </span>
                  )}
                </div>
                <div className="flex-none w-50">{t('Last seen')}</div>
                <div className="flex-none w-5" />
              </div>
              <div className="divide-y divide-gray-200">
                {pageRows.map((row) => (
                  <TimeConnectedRow key={row.original.displayId} row={row.original} />
                ))}
              </div>
            </div>
            <div className="flex-none">
              <DataTablePagination
                table={table}
                pagination={table.getState().pagination}
                pageCount={table.getPageCount()}
                loading={isFetching}
              />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
