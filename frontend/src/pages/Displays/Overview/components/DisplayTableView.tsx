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
  RowSelectionState,
  SortingState,
} from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { Eye, Settings } from 'lucide-react';
import { type ComponentProps, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { getDisplayStatusInfo, PAGE_SIZE_OPTIONS } from '../OverviewConfig';

import DisplayStatusBadge from './DisplayStatusBadge';

import { DataTable } from '@/components/ui/table/DataTable';
import { ActionsCell, TextCell } from '@/components/ui/table/cells';
import { useDateFormatter, type DateFormatter } from '@/hooks/useDateFormatter';
import type { Display } from '@/types/display';
import type { ActionItem } from '@/types/table';
import { getStorageFreePercentLabel } from '@/utils/formatters';

interface DisplayTableViewProps {
  displays: Display[];
  totalCount: number;
  isLoading: boolean;
  isFetching: boolean;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  onManage: (display: Display) => void;
  onLiveView: (display: Display) => void;
}

interface GetColumnsParams {
  t: TFunction;
  formatRelative: DateFormatter['formatRelative'];
  onManage: (display: Display) => void;
  onLiveView: (display: Display) => void;
}

// Same row-actions convention every other DataTable page uses (see
// DaypartConfig.tsx's getDaypartItemActions) — quick-action icon buttons via
// ActionsCell, rather than this page's own bespoke icon+label link buttons.
function getOverviewTableActions({
  t,
  onManage,
  onLiveView,
}: Pick<GetColumnsParams, 't' | 'onManage' | 'onLiveView'>): (display: Display) => ActionItem[] {
  return (display) => [
    {
      label: t('Manage'),
      icon: Settings,
      onClick: () => onManage(display),
      isQuickAction: true,
    },
    {
      label: t('Screenshots'),
      icon: Eye,
      onClick: () => onLiveView(display),
      isQuickAction: true,
      variant: 'primary',
    },
  ];
}

// Same status/last-accessed vocabulary as the card grid, adapted to the
// columns this page's data actually has (no Proof of Play figures yet — see
// ProofOfPlayPanel). Sorting isn't wired up server-side for this page (see
// useOverviewDisplays), so every column disables it rather than showing sort
// arrows that don't actually resort anything.
function getOverviewTableColumns({
  t,
  formatRelative,
  onManage,
  onLiveView,
}: GetColumnsParams): ColumnDef<Display>[] {
  const getRowActions = getOverviewTableActions({ t, onManage, onLiveView });

  return [
    {
      id: 'display',
      header: t('Display'),
      enableSorting: false,
      size: 220,
      cell: ({ row }) => (
        <TextCell weight="bold" truncate>
          {row.original.display}
        </TextCell>
      ),
    },
    {
      id: 'displayGroup',
      header: t('Display Group'),
      enableSorting: false,
      size: 160,
      cell: ({ row }) => (
        <TextCell>{row.original.displayGroups?.[0]?.displayGroup ?? '—'}</TextCell>
      ),
    },
    {
      id: 'status',
      header: t('Status'),
      enableSorting: false,
      size: 140,
      cell: ({ row }) => {
        const { bucket, colors, badgeLabel } = getDisplayStatusInfo(
          row.original,
          t,
          formatRelative,
        );
        return (
          <DisplayStatusBadge
            bucket={bucket}
            colors={colors}
            label={badgeLabel}
            className="w-fit"
          />
        );
      },
    },
    {
      id: 'lastAccessed',
      header: t('Last Accessed'),
      enableSorting: false,
      size: 140,
      cell: ({ row }) => (
        <TextCell className="whitespace-nowrap">
          {getDisplayStatusInfo(row.original, t, formatRelative).lastSeenLabel}
        </TextCell>
      ),
    },
    {
      id: 'storageFree',
      header: t('Storage Free'),
      enableSorting: false,
      size: 110,
      cell: ({ row }) => (
        <TextCell className="tabular-nums">
          {getStorageFreePercentLabel(
            row.original.storageAvailableSpace,
            row.original.storageTotalSpace,
          ) || '—'}
        </TextCell>
      ),
    },
    {
      id: 'currentLayout',
      header: t('Currently Playing'),
      enableSorting: false,
      size: 200,
      cell: ({ row }) => <TextCell truncate>{row.original.currentLayout || '-'}</TextCell>,
    },
    {
      id: 'tableActions',
      header: '',
      enableSorting: false,
      enableResizing: false,
      size: 90,
      minSize: 90,
      maxSize: 90,
      cell: ({ row }) => (
        <ActionsCell
          row={row}
          actions={getRowActions(row.original) as ComponentProps<typeof ActionsCell>['actions']}
        />
      ),
    },
  ];
}

// Renders the Overview page's table view on top of the same shared
// DataTable component the rest of the CMS's grids use, rather than a
// bespoke <table>. The page's own search box + FilterInputs panel above
// (Overview.tsx) remain the only filter UI — DataTable's built-in
// globalFilter is left inert (manualFiltering, never written to) and its
// toolbar is hidden so it doesn't grow a second one.
export default function DisplayTableView({
  displays,
  totalCount,
  isLoading,
  isFetching,
  pagination,
  onPaginationChange,
  onManage,
  onLiveView,
}: DisplayTableViewProps) {
  const { t } = useTranslation();
  const { formatRelative } = useDateFormatter();

  const [sorting, setSorting] = useState<SortingState>([]);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

  const columns = getOverviewTableColumns({ t, formatRelative, onManage, onLiveView });
  const pageCount = Math.max(1, Math.ceil(totalCount / pagination.pageSize));

  return (
    <DataTable
      columns={columns}
      data={displays}
      pageCount={pageCount}
      rowCount={totalCount}
      pagination={pagination}
      onPaginationChange={onPaginationChange}
      sorting={sorting}
      onSortingChange={setSorting}
      globalFilter=""
      onGlobalFilterChange={() => {}}
      rowSelection={rowSelection}
      onRowSelectionChange={setRowSelection}
      enableSelection={false}
      hideToolbar
      loading={isLoading || isFetching}
      pageSizeOptions={PAGE_SIZE_OPTIONS}
    />
  );
}
