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

import type { ColumnDef, PaginationState, SortingState } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import MediaTypeChart, { buildChartItems } from './components/MediaTypeChart';
import TidyLibraryModal from './components/TidyLibraryModal';
import { useMediaDashboardStats } from './hooks/useMediaDashboardStats';
import { useUnreleasedMedia, useUnusedMedia } from './hooks/useUnusedMedia';

import list from '@/assets/dashboard/list.svg';
import server from '@/assets/dashboard/server.svg';
import trash from '@/assets/dashboard/trash-can.svg';
import Button from '@/components/ui/Button';
import { DataTable } from '@/components/ui/table/DataTable';
import { StatusCell, TextCell } from '@/components/ui/table/cells';
import { getStatusTypeFromMediaType } from '@/pages/Library/Media/MediaConfig';
import type { Media } from '@/types/media';

function getUnusedColumns(t: TFunction): ColumnDef<Media>[] {
  return [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      cell: (info) => (
        <TextCell truncate weight="bold">
          {info.getValue<string>()}
        </TextCell>
      ),
    },
    {
      accessorKey: 'mediaType',
      header: t('Type'),
      cell: (info) => {
        const value = info.getValue() as string;
        return <StatusCell label={value} type={getStatusTypeFromMediaType(value)} />;
      },
    },
    {
      accessorKey: 'fileSizeFormatted',
      header: t('Size'),
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
  ];
}

function getUnreleasedColumns(t: TFunction): ColumnDef<Media>[] {
  return [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'name',
      header: t('Name'),
      cell: (info) => (
        <TextCell truncate weight="bold">
          {info.getValue<string>()}
        </TextCell>
      ),
    },
  ];
}

interface StatCardProps {
  icon: string;
  value: ReactNode;
  label: ReactNode;
}

function StatCard({ icon, value, label }: StatCardProps) {
  return (
    <div className="flex items-center space-x-4 rounded-lg border border-gray-200 bg-slate-50 p-4">
      <img src={icon} alt="" className="h-10 w-auto text-xibo-blue-600" />
      <div>
        <div className="text-[20px] font-semibold text-gray-800">{value}</div>
        <div className="text-[16px] text-gray-500">{label}</div>
      </div>
    </div>
  );
}

export default function MediaDashboard() {
  const { t } = useTranslation();
  const { data, isLoading, refetch: refetchStats } = useMediaDashboardStats();
  const [showTidyModal, setShowTidyModal] = useState(false);

  // Unused media table state
  const [unusedPagination, setUnusedPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [unusedSorting, setUnusedSorting] = useState<SortingState>([]);
  const { data: unusedData, isFetching: unusedFetching } = useUnusedMedia(
    unusedPagination,
    unusedSorting,
  );

  // Unreleased media table state
  const [unreleasedPagination, setUnreleasedPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 5,
  });
  const [unreleasedSorting, setUnreleasedSorting] = useState<SortingState>([]);
  const { data: unreleasedData, isFetching: unreleasedFetching } = useUnreleasedMedia(
    unreleasedPagination,
    unreleasedSorting,
  );

  const library = data?.library;
  const Loading = <div className="h-4 w-12 animate-pulse rounded bg-gray-200" />;

  const unusedColumns = getUnusedColumns(t);
  const unreleasedColumns = getUnreleasedColumns(t);
  const unusedRows = unusedData?.rows ?? [];
  const unusedPageCount = Math.ceil((unusedData?.totalCount ?? 0) / unusedPagination.pageSize);
  const unreleasedRows = unreleasedData?.rows ?? [];
  const unreleasedPageCount = Math.ceil(
    (unreleasedData?.totalCount ?? 0) / unreleasedPagination.pageSize,
  );

  const countItems = library ? buildChartItems(library.types, 'count') : [];
  const sizeItems = library ? buildChartItems(library.types, 'size') : [];

  return (
    <section className="flex flex-col gap-5 p-5 min-h-screen h-screen max-h-max">
      {/* Top Stats */}
      <div className="grid grid-cols-3 gap-5">
        <StatCard
          icon={list}
          value={isLoading ? Loading : (library?.countOf?.toLocaleString() ?? '—')}
          label={t('Library Count')}
        />
        <StatCard
          icon={server}
          value={isLoading ? Loading : (library?.size ?? '—')}
          label={t('Library Size')}
        />

        <StatCard
          icon={trash}
          value={t('Library')}
          label={
            <Button
              variant="tertiary"
              rightIcon={ArrowRight}
              className="p-0"
              onClick={() => setShowTidyModal(true)}
            >
              {t('Tidy Library')}
            </Button>
          }
        />
      </div>

      {/* Charts */}
      <div className="grid grid-cols-2 gap-5">
        <MediaTypeChart
          title={t('No. of Media Items')}
          items={countItems}
          centerValue={library?.countOf?.toLocaleString() ?? '—'}
          centerLabel={t('Total Items')}
          isLoading={isLoading}
        />
        <MediaTypeChart
          title={t('Size of Media Items')}
          items={sizeItems}
          centerValue={library?.size ?? '—'}
          centerLabel={t('Total Size')}
          isLoading={isLoading}
        />
      </div>

      {/* Tables */}
      <div className="flex-1 grid grid-cols-2 gap-5">
        <div className="rounded-lg border border-gray-200 bg-white p-5 min-h-full">
          <h3 className="mb-4 text-sm font-semibold uppercase text-gray-800">
            {t('Unused Media')}
          </h3>
          <DataTable
            columns={unusedColumns}
            data={unusedRows}
            pageCount={unusedPageCount}
            pagination={unusedPagination}
            onPaginationChange={setUnusedPagination}
            sorting={unusedSorting}
            onSortingChange={setUnusedSorting}
            globalFilter=""
            onGlobalFilterChange={() => {}}
            rowSelection={{}}
            onRowSelectionChange={() => {}}
            loading={unusedFetching}
            hideToolbar
            enableSelection={false}
            viewMode="table"
            getRowId={(row) => String(row.mediaId)}
          />
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-5 min-h-full">
          <h3 className="mb-4 text-sm font-semibold uppercase text-gray-800">
            {t('Unreleased Media')}
          </h3>
          <DataTable
            columns={unreleasedColumns}
            data={unreleasedRows}
            pageCount={unreleasedPageCount}
            pagination={unreleasedPagination}
            onPaginationChange={setUnreleasedPagination}
            sorting={unreleasedSorting}
            onSortingChange={setUnreleasedSorting}
            globalFilter=""
            onGlobalFilterChange={() => {}}
            rowSelection={{}}
            onRowSelectionChange={() => {}}
            loading={unreleasedFetching}
            hideToolbar
            enableSelection={false}
            viewMode="table"
            getRowId={(row) => String(row.mediaId)}
          />
        </div>
      </div>

      {showTidyModal && (
        <TidyLibraryModal
          onClose={() => setShowTidyModal(false)}
          onSuccess={() => {
            setShowTidyModal(false);
            refetchStats();
          }}
          unusedCount={unusedData?.totalCount ?? 0}
        />
      )}
    </section>
  );
}
