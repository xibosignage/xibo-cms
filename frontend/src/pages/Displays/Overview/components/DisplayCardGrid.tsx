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
  type OnChangeFn,
  type PaginationState,
} from '@tanstack/react-table';
import { LayoutGrid, List } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { getBucketLabel, PAGE_SIZE_OPTIONS } from '../OverviewConfig';

import DisplayCard from './DisplayCard';
import DisplayTableView from './DisplayTableView';

import Button from '@/components/ui/Button';
import { DataTablePagination } from '@/components/ui/table/DataTablePagination';
import type { Display } from '@/types/display';
import type { DisplayOverviewBucket } from '@/types/displayOverview';

export type OverviewViewMode = 'cards' | 'table';

interface DisplayCardGridProps {
  displays: Display[];
  totalCount: number;
  isLoading: boolean;
  isFetching: boolean;
  activeBucket: DisplayOverviewBucket | null;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  onManage: (display: Display) => void;
  onLiveView: (display: Display) => void;
  viewMode: OverviewViewMode;
  onViewModeChange: (mode: OverviewViewMode) => void;
}

function DisplayCardSkeleton() {
  return (
    <div className="flex flex-col rounded-xl border border-gray-200 bg-white overflow-hidden">
      <div className="h-24 w-full animate-pulse bg-gray-200" />
      <div className="flex flex-col gap-1.5 px-3 pt-2 pb-1.5">
        <div className="h-3.5 w-3/4 animate-pulse rounded bg-gray-200" />
        <div className="h-3 w-1/2 animate-pulse rounded bg-gray-200" />
      </div>
      <div className="flex flex-col gap-2 px-3 pb-2">
        <div className="h-3 w-full animate-pulse rounded bg-gray-200" />
        <div className="h-3 w-2/3 animate-pulse rounded bg-gray-200" />
      </div>
      <div className="mt-auto h-8 border-t border-gray-200 bg-gray-50" />
    </div>
  );
}

export default function DisplayCardGrid({
  displays,
  totalCount,
  isLoading,
  isFetching,
  activeBucket,
  pagination,
  onPaginationChange,
  onManage,
  onLiveView,
  viewMode,
  onViewModeChange,
}: DisplayCardGridProps) {
  const { t } = useTranslation();

  const pageCount = Math.max(1, Math.ceil(totalCount / pagination.pageSize));

  // Reuses the same TanStack Table + DataTablePagination pairing the
  // Gallery/Card views elsewhere in the CMS already use (see DataGrid.tsx),
  // rather than a bespoke page-window control just for this page. No columns
  // are defined — the table instance only exists to drive pagination state.
  const table = useReactTable({
    data: displays,
    columns: [],
    pageCount,
    rowCount: totalCount,
    state: { pagination },
    onPaginationChange,
    manualPagination: true,
    getCoreRowModel: getCoreRowModel(),
  });

  const viewToggle = (
    <div className="flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-0.5">
      <Button
        variant={viewMode === 'cards' ? 'primary' : 'tertiary'}
        leftIcon={LayoutGrid}
        aria-pressed={viewMode === 'cards'}
        onClick={() => onViewModeChange('cards')}
        className="min-w-0 px-3 py-1.5 text-xs"
      >
        {t('Cards')}
      </Button>
      <Button
        variant={viewMode === 'table' ? 'primary' : 'tertiary'}
        leftIcon={List}
        aria-pressed={viewMode === 'table'}
        onClick={() => onViewModeChange('table')}
        className="min-w-0 px-3 py-1.5 text-xs"
      >
        {t('Table')}
      </Button>
    </div>
  );

  const headerControls = <div className="flex items-center justify-end gap-3">{viewToggle}</div>;

  // The table view hands off to the shared DataTable component entirely — it
  // owns its own loading overlay, empty state, and pagination bar (matching
  // every other grid in the CMS), so it skips the card-specific skeleton/empty
  // states and shared pagination bar below. No flex-1/min-h-0 here (unlike
  // the other branches) — DataTable's internal `flex-1 min-h-0` chain is
  // meant to fill a fixed-height ancestor and scroll internally; without
  // that constraint it instead sizes to its content, so the table's height
  // grows/shrinks with however many rows the current page size shows, and
  // the page's own overflow-y-auto (Overview.tsx) scrolls once it overflows.
  if (viewMode === 'table') {
    return (
      <div className="flex flex-col gap-4">
        {headerControls}
        <DisplayTableView
          displays={displays}
          totalCount={totalCount}
          isLoading={isLoading}
          isFetching={isFetching}
          pagination={pagination}
          onPaginationChange={onPaginationChange}
          onManage={onManage}
          onLiveView={onLiveView}
        />
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="flex flex-1 flex-col gap-4 min-h-0">
        {headerControls}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
          {Array.from({ length: pagination.pageSize }, (_, i) => (
            <DisplayCardSkeleton key={i} />
          ))}
        </div>
      </div>
    );
  }

  if (displays.length === 0) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white py-16">
        <div className="inline-flex size-15.5 items-center justify-center rounded-full border-7 border-gray-50 bg-gray-100 text-gray-500">
          <LayoutGrid className="size-5 shrink-0" />
        </div>
        <h3 className="text-lg font-semibold text-gray-800">
          {activeBucket
            ? t('No displays match "{{bucket}}"', { bucket: getBucketLabel(t, activeBucket) })
            : t('No displays found')}
        </h3>
        <p className="text-gray-500">{t('There are no displays to show yet.')}</p>
      </div>
    );
  }

  return (
    <div className="flex flex-1 flex-col gap-4 min-h-0">
      {headerControls}

      <div className={`transition-opacity ${isFetching ? 'opacity-60' : ''}`}>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
          {displays.map((display) => (
            <DisplayCard
              key={display.displayId}
              display={display}
              onManage={onManage}
              onLiveView={onLiveView}
            />
          ))}
        </div>
      </div>

      {totalCount > 0 && (
        <DataTablePagination
          table={table}
          pagination={pagination}
          pageCount={pageCount}
          pageSizeOptions={PAGE_SIZE_OPTIONS}
          loading={isFetching}
        />
      )}
    </div>
  );
}
