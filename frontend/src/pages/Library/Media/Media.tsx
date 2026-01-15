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
.*/
import {
  type ColumnDef,
  type SortingState,
  type PaginationState,
  type OnChangeFn,
  type RowSelectionState,
} from '@tanstack/react-table';
import {
  Check,
  X,
  Search,
  Filter,
  Folder,
  Plus,
  Edit,
  Download,
  CopyCheck,
  UserPlus2,
  CalendarClock,
  FolderInput,
  Info,
  Trash2,
} from 'lucide-react';
import { useEffect, useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@/components/ui/table/DataTable';
import { Media as MediaCell, Text, Status, Actions, Tags } from '@/components/ui/table/TableCells';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchMedia } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

interface ApiTag {
  tagId: number;
  tag: string;
}

type MediaType = 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';

const formatDuration = (seconds: number) => {
  if (!seconds) {
    return '-';
  }

  return new Date(seconds * 1000).toISOString().slice(11, 19);
};

export default function Media() {
  const { t } = useTranslation();

  const [data, setData] = useState<MediaRow[]>([]);
  const [error, setError] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(true);

  const [pageCount, setPageCount] = useState(0);
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const [sorting, setSorting] = useState<SortingState>([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

  const debouncedFilter = useDebounce(globalFilter, 500);

  useEffect(() => {
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  }, [debouncedFilter]);

  const handlePaginationChange: OnChangeFn<PaginationState> = (updaterOrValue) => {
    setPagination((prev) => {
      const newPagination =
        typeof updaterOrValue === 'function' ? updaterOrValue(prev) : updaterOrValue;
      if (newPagination.pageSize !== prev.pageSize) {
        return { ...newPagination, pageIndex: 0 };
      }
      return newPagination;
    });
  };

  useEffect(() => {
    const controller = new AbortController();

    async function load() {
      setLoading(true);
      try {
        const sortState = sorting?.[0];
        const startOffset = pagination.pageIndex * pagination.pageSize;

        const result = await fetchMedia({
          start: startOffset,
          length: pagination.pageSize,
          media: debouncedFilter,
          sortBy: sortState?.id,
          sortDir: sortState?.desc ? 'desc' : 'asc',
          signal: controller.signal,
        });

        setData(result.rows || []);
        setRowSelection({});

        const totalPages = Math.ceil(result.totalCount / pagination.pageSize);
        setPageCount(totalPages);
        setError('');
      } catch (err) {
        if (!controller.signal.aborted) {
          console.error('Failed to load media', err);
          setError(err instanceof Error ? err.message : 'Unknown error');
          setData([]);
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }
    load();
    return () => controller.abort();
  }, [pagination, sorting, debouncedFilter]);

  const handleDelete = () => {
    const selectedItems = Object.keys(rowSelection)
      .map((index) => data[Number(index)])
      .filter((item): item is MediaRow => item !== undefined);

    if (selectedItems.length === 0) {
      return;
    }

    const message = `${t('Are you sure you want to delete these items?')}\n(${selectedItems.length} selected)`;

    if (window.confirm(message)) {
      console.log(
        'Deleting items:',
        selectedItems.map((i) => i.mediaId),
      );
      setRowSelection({});
    }
  };

  const columns = useMemo<ColumnDef<MediaRow>[]>(
    () => [
      {
        id: 'select',
        header: ({ table }) => (
          <div className="px-1">
            <input
              type="checkbox"
              checked={table.getIsAllPageRowsSelected()}
              onChange={(e) => table.toggleAllPageRowsSelected(!!e.target.checked)}
              className="shrink-0 mt-0.5 border-gray-200 text-gray-600 focus:ring-gray-500 disabled:opacity-50 disabled:pointer-events-none"
            />
          </div>
        ),
        cell: ({ row }) => (
          <div className="px-1">
            <input
              type="checkbox"
              checked={row.getIsSelected()}
              onChange={(e) => row.toggleSelected(!!e.target.checked)}
              className="shrink-0 mt-0.5 border-gray-200 text-gray-600 focus:ring-gray-500 disabled:opacity-50 disabled:pointer-events-none"
            />
          </div>
        ),
        enableSorting: false,
        enableHiding: false,
        size: 55,
      },
      {
        accessorKey: 'mediaId',
        header: t('ID'),
        size: 80,
        cell: (info) => <Text className="font-mono text-xs">{info.getValue<number>()}</Text>,
      },
      {
        accessorKey: 'thumbnail',
        header: t('Thumbnail'),
        size: 100,
        enableSorting: false,
        cell: (info) => (
          <MediaCell
            id={info.row.original.mediaId}
            thumb={info.row.original.thumbnail}
            alt={info.row.original.name}
            mediaType={(info.row.original.mediaType as MediaType) || 'other'}
          />
        ),
      },
      {
        accessorKey: 'name',
        header: t('Name'),
        size: 250,
        enableHiding: false,
        cell: (info) => <Text>{info.getValue<string>()}</Text>,
      },
      {
        accessorKey: 'mediaType',
        header: t('Type'),
        size: 100,
        cell: (info) => <Status label={info.getValue() as string} type="neutral" />,
      },
      {
        accessorKey: 'tags',
        header: t('Tags'),
        size: 200,
        cell: (info) => {
          const tags = info.getValue<ApiTag[]>() || [];
          const formattedTags = tags.map((tag) => ({
            id: tag.tagId,
            label: tag.tag,
          }));
          return <Tags tags={formattedTags} />;
        },
      },
      {
        id: 'formattedDuration',
        accessorKey: 'duration',
        header: t('Duration'),
        size: 100,
        cell: (info) => <Text>{formatDuration(info.getValue<number>())}</Text>,
      },
      {
        id: 'durationSeconds',
        accessorKey: 'duration',
        header: t('Duration (s)'),
        size: 100,
        cell: (info) => <Text>{info.getValue<number>()}</Text>,
      },
      {
        accessorKey: 'fileSizeFormatted',
        header: t('Size'),
        size: 100,
        cell: (info) => <Text>{info.getValue<string>()}</Text>,
      },
      {
        accessorKey: 'fileSize',
        header: t('Size (bytes)'),
        size: 120,
        cell: (info) => (
          <Text className="font-mono text-xs">{info.getValue<number>().toLocaleString()}</Text>
        ),
      },
      {
        id: 'resolution',
        header: t('Resolution'),
        size: 120,
        accessorFn: (row) => {
          if (row.width && row.height) return `${row.width}x${row.height}`;
          return '';
        },
        cell: (info) => <Text>{info.getValue<string>() || '-'}</Text>,
      },
      {
        accessorKey: 'owner',
        header: t('Owner'),
        size: 150,
        cell: (info) => <Text>{info.getValue<string>()}</Text>,
      },
      {
        accessorKey: 'groupsWithPermissions',
        header: t('Sharing'),
        size: 150,
        cell: (info) => {
          const groups = info.getValue() as string;
          return <Text className="italic text-gray-500">{groups || t('Private')}</Text>;
        },
      },
      {
        accessorKey: 'revised',
        header: t('Revised'),
        size: 80,
        cell: (info) => <Text>{info.getValue<number>()}</Text>,
      },
      {
        accessorKey: 'released',
        header: t('Released'),
        size: 100,
        cell: (info) => {
          const isReleased = info.getValue() === 1;
          return isReleased ? (
            <Check className="w-4 h-4 text-green-600" />
          ) : (
            <X className="w-4 h-4 text-gray-400" />
          );
        },
      },
      {
        accessorKey: 'fileName',
        header: t('File Name'),
        size: 200,
        cell: (info) => (
          <Text className="truncate" title={info.getValue() as string}>
            {info.getValue<string>()}
          </Text>
        ),
      },
      {
        accessorKey: 'enableStat',
        header: t('Stats?'),
        size: 100,
        cell: (info) => <Status label={info.getValue() as string} type="neutral" />,
      },
      {
        accessorKey: 'createdDt',
        header: t('Created'),
        size: 180,
        cell: (info) => <Text subtext="Date">{info.getValue<string>()}</Text>,
      },
      {
        accessorKey: 'modifiedDt',
        header: t('Modified'),
        size: 180,
        cell: (info) => <Text subtext="Date">{info.getValue<string>()}</Text>,
      },
      {
        accessorKey: 'expires',
        header: t('Expires'),
        size: 180,
        cell: (info) => {
          const val = info.getValue() as number;
          if (val === 0) return <span className="text-gray-400">-</span>;
          return <Text>{val}</Text>;
        },
      },
      {
        id: 'actions',
        header: '',
        size: 140,
        enableHiding: false,
        cell: ({ row }) => (
          <Actions
            row={row}
            actions={[
              // Quick Actions
              {
                label: t('Edit'),
                icon: <Edit className="w-4 h-4" />,
                onClick: (data) => console.log('Edit', data.mediaId),
                isQuickAction: true,
              },
              {
                label: t('Download'),
                icon: <Download className="w-4 h-4" />,
                onClick: (data) => console.log('Download', data.mediaId),
                isQuickAction: true,
              },

              // Dropdown Menu Actions
              {
                label: t('Edit'),
                icon: <Edit className="w-4 h-4" />,
                onClick: (data) => console.log('Edit', data.mediaId),
              },
              {
                label: t('Make a Copy'),
                icon: <CopyCheck className="w-4 h-4" />,
                onClick: (data) => console.log('Make a Copy', data.mediaId),
              },
              {
                label: t('Move'),
                icon: <FolderInput className="w-4 h-4" />,
                onClick: (data) => console.log('Move', data.mediaId),
              },
              {
                label: t('Share'),
                icon: <UserPlus2 className="w-4 h-4" />,
                onClick: (data) => console.log('Share', data.mediaId),
              },
              {
                label: t('Download'),
                icon: <Download className="w-4 h-4" />,
                onClick: (data) => console.log('Download', data.mediaId),
              },
              {
                label: t('Schedule'),
                icon: <CalendarClock className="w-4 h-4" />,
                onClick: (data) => console.log('Schedule', data.mediaId),
              },
              {
                label: t('Details'),
                icon: <Info className="w-4 h-4" />,
                onClick: (data) => console.log('Details', data.mediaId),
              },
              {
                isSeparator: true,
              },
              {
                label: t('Enable Stats Collection'),
                onClick: (data) => console.log('Enable Stats', data.mediaId),
              },
              {
                label: t('Usage Report'),
                onClick: (data) => console.log('Usage Report', data.mediaId),
              },
              {
                isSeparator: true,
              },
              {
                label: t('Delete'),
                icon: <Trash2 className="w-4 h-4" />,
                onClick: handleDelete,
                variant: 'danger',
              },
            ]}
          />
        ),
      },
    ],
    [t],
  );

  return (
    <section className="space-y-4 p-4 md:p-6 min-h-screen">
      {/* TODO: Navigation tabs & Buttons */}
      <div className="flex flex-col md:flex-row justify-between items-end md:items-center gap-4 pb-2">
        {/* TODO: Navigation tabs */}
        <nav className="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
          {['Playlists', 'Media', 'Datasets', 'MenuBoards'].map((tab) => (
            <button
              key={tab}
              type="button"
              className={`py-2 px-1 inline-flex items-center gap-x-2 border-b-2 whitespace-nowrap focus:outline-none transition-colors ${
                tab === 'Media'
                  ? 'text-gray-600'
                  : 'border-transparent text-gray-500 hover:text-gray-600 hover:border-gray-300'
              }`}
              aria-current={tab === 'Media' ? 'page' : undefined}
            >
              {t(tab)}
            </button>
          ))}
        </nav>

        {/* TODO: Buttons */}
        <div className="flex items-center gap-2 mb-2 md:mb-0">
          <button className="py-2 px-4 inline-flex justify-center items-center gap-2 border border-transparent bg-gray-600 text-white hover:bg-gray-700 transition-all">
            <Plus className="w-4 h-4" />
            {t('Add Media')}
          </button>
        </div>
      </div>

      {/* TODO: Folder control & Filters */}
      <div className="flex flex-col md:flex-row justify-between items-center gap-4">
        {/* TODO: Folder Control */}
        <div className="w-full md:w-auto">
          <button className="py-2 px-3 w-full md:w-auto inline-flex justify-center md:justify-start items-center gap-x-2 border border-gray-200 bg-white text-gray-800 hover:bg-gray-50">
            <Folder className="w-4 h-4 text-gray-500 fill-gray-100" />
            {t('Folders')}
          </button>
        </div>

        {/* TODO: Filters */}
        <div className="flex items-center gap-2 w-full md:w-auto">
          {/* Search */}
          <div className="relative w-full md:w-64">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-4 h-4 text-gray-400" />
            </div>
            <input
              value={globalFilter}
              onChange={(e) => setGlobalFilter(e.target.value)}
              placeholder={t('Search media...')}
              className="py-2 px-3 pl-10 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
            />
          </div>

          {/* Inline filter button */}
          <button className="p-2 inline-flex justify-center items-center gap-2 border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-all shrink-0">
            <Filter className="w-4 h-4" />
          </button>
        </div>
      </div>
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
          {error}
        </div>
      )}

      <DataTable
        columns={columns}
        data={data}
        pageCount={pageCount}
        pagination={pagination}
        onPaginationChange={handlePaginationChange}
        sorting={sorting}
        onSortingChange={setSorting}
        globalFilter={globalFilter}
        onGlobalFilterChange={setGlobalFilter}
        loading={loading}
        rowSelection={rowSelection}
        onRowSelectionChange={setRowSelection}
        columnPinning={{
          left: ['select'],
          right: ['actions'],
        }}
        initialState={{
          columnVisibility: {
            mediaId: false,
            durationSeconds: false,
            fileSize: false,
            createdDt: false,
            modifiedDt: false,
            groupsWithPermissions: false,
            revised: false,
            released: false,
            fileName: false,
            expires: false,
            enableStat: false,
            owner: false,
          },
        }}
        selectionActions={
          <button
            onClick={handleDelete}
            className="py-1.5 px-3 inline-flex items-center gap-x-2 border border-gray-200 bg-white text-red-600 hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none"
          >
            {t('Delete Selected')}
          </button>
        }
      />
    </section>
  );
}
