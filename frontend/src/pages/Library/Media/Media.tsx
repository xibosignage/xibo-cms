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
  Search,
  Filter,
  Folder,
  Edit,
  Download,
  CopyCheck,
  UserPlus2,
  CalendarClock,
  FolderInput,
  Info,
  Trash2,
  FilterX,
  MoreVertical,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/media/FilterInputs';
import MediaTopbar from '@/components/ui/media/MediaTopNav';
import { DataTable } from '@/components/ui/table/DataTable';
import type { DataTableBulkAction } from '@/components/ui/table/DataTableBulkActions';
import {
  MediaCell,
  CheckMarkCell,
  TextCell,
  StatusCell,
  ActionsCell,
  TagsCell,
} from '@/components/ui/table/cells';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchMedia, deleteMedia } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

interface ApiTag {
  tagId: number;
  tag: string;
}

export interface FilterInput {
  type: string;
  owner: string;
  userGroup: string;
  orientation: string;
  retired: string;
  lastModified: string;
}

type MediaType = 'image' | 'video' | 'audio' | 'pdf' | 'archive' | 'other';

const MEDIA_NAV = ['Playlists', 'Media', 'Datasets', 'Menu Boards'];

const formatDuration = (seconds: number) => {
  if (!seconds) {
    return '-';
  }

  return new Date(seconds * 1000).toISOString().slice(11, 19);
};

const getStatusTypeFromMediaType = (mediaType: string) => {
  const type = mediaType?.toLowerCase();

  switch (type) {
    case 'image':
    case 'video':
    case 'audio':
      return 'info';
    case 'pdf':
    case 'powerpoint':
      return 'danger';
    case 'flash':
    case 'htmlpackage':
      return 'success';
    default:
      return 'neutral';
  }
};

export default function Media() {
  const { t } = useTranslation();

  const [data, setData] = useState<MediaRow[]>([]);
  const [error, setError] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshTrigger, setRefreshTrigger] = useState(0);

  const [pageCount, setPageCount] = useState(0);
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const [sorting, setSorting] = useState<SortingState>([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [activeTab, setActiveTab] = useState('Media');
  const [openFilter, setOpenFilter] = useState(false);

  const debouncedFilter = useDebounce(globalFilter, 500);

  const [filterInputs, setFilterInput] = useState<FilterInput>({
    type: '',
    owner: '',
    userGroup: '',
    orientation: '',
    retired: '',
    lastModified: '',
  });

  const bulkActions: DataTableBulkAction<MediaRow>[] = [
    {
      label: t('Move'),
      icon: FolderInput,
      onClick: async (selectedItems) => {
        console.log('Move');
        console.log(selectedItems);
      },
    },
    {
      label: t('Share'),
      icon: UserPlus2,
      onClick: async (selectedItems) => {
        console.log('Share');
        console.log(selectedItems);
      },
    },
    {
      label: t('Download'),
      icon: Download,
      onClick: async (selectedItems) => {
        console.log('Download');
        console.log(selectedItems);
      },
    },
    {
      label: t('Delete Selected'),
      icon: Trash2,
      onClick: async (selectedItems) => {
        if (selectedItems.length === 0) {
          return;
        }

        const message = `${t('Are you sure you want to delete these items?')}\n(${selectedItems.length} selected)`;
        if (window.confirm(message)) {
          setLoading(true);
          try {
            await Promise.all(selectedItems.map((item) => deleteMedia(item.mediaId)));
            setRowSelection({});
            setRefreshTrigger((prev) => prev + 1);
          } catch (err) {
            console.error('Bulk delete error', err);
            alert(t('Some items could not be deleted. Check if they are in use.'));
            setRefreshTrigger((prev) => prev + 1);
          } finally {
            setLoading(false);
          }
        }
      },
    },
    {
      label: t('More'),
      icon: MoreVertical,
      onClick: async (selectedItems) => {
        console.log('More?');
        console.log(selectedItems);
      },
    },
  ];

  // Reset pagination when filter changes
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

  const handleRefresh = () => {
    setRefreshTrigger((prev) => prev + 1);
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
  }, [pagination, sorting, debouncedFilter, refreshTrigger]);

  // Handle delete
  const handleDelete = async (id: number) => {
    try {
      await deleteMedia(id);
      setRefreshTrigger((prev) => prev + 1);
    } catch (err) {
      console.error('Failed to delete media!', err);
      alert(t('Failed to delete media!'));
    }
  };

  const handleFilterChange = (name: string, value: string) => {
    setFilterInput((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const columns: ColumnDef<MediaRow>[] = [
    {
      accessorKey: 'mediaId',
      header: t('ID'),
      size: 80,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'thumbnail',
      header: t('Thumbnail'),
      size: 150,
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
      cell: (info) => <TextCell weight="bold">{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'mediaType',
      header: t('Type'),
      size: 100,
      cell: (info) => {
        const value = info.getValue() as string;
        return <StatusCell label={value} type={getStatusTypeFromMediaType(value)} />;
      },
    },
    {
      accessorKey: 'tags',
      header: t('Tags'),
      enableSorting: false,
      size: 200,
      cell: (info) => {
        const tags = info.getValue<ApiTag[]>() || [];
        const formattedTags = tags.map((tag) => ({
          id: tag.tagId,
          label: tag.tag,
        }));
        return <TagsCell tags={formattedTags} />;
      },
    },
    {
      id: 'formattedDuration',
      accessorKey: 'duration',
      header: t('Duration'),
      size: 140,
      cell: (info) => <TextCell>{formatDuration(info.getValue<number>())}</TextCell>,
    },
    {
      id: 'durationSeconds',
      accessorKey: 'duration',
      header: t('Duration (s)'),
      size: 140,
      cell: (info) => <TextCell>{info.getValue<number>()}</TextCell>,
    },
    {
      accessorKey: 'fileSizeFormatted',
      header: t('Size'),
      size: 100,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'fileSize',
      header: t('Size (bytes)'),
      size: 150,
      cell: (info) => (
        <TextCell className="font-mono text-sm">
          {info.getValue<number>().toLocaleString()}
        </TextCell>
      ),
    },
    {
      id: 'resolution',
      header: t('Resolution'),
      size: 150,
      accessorFn: (row) => {
        if (row.width && row.height) return `${row.width}x${row.height}`;
        return '';
      },
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'owner',
      header: t('Owner'),
      size: 150,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'groupsWithPermissions',
      enableSorting: false,
      header: t('Sharing'),
      size: 150,
      cell: (info) => {
        const groups = info.getValue() as string;
        return <TextCell className="italic text-gray-500">{groups || t('Private')}</TextCell>;
      },
    },
    {
      accessorKey: 'revised',
      header: t('Revised'),
      size: 120,
      cell: (info) => <CheckMarkCell active={(info.getValue<number>() === 1) as boolean} />,
    },
    {
      accessorKey: 'released',
      header: t('Released'),
      size: 120,
      cell: (info) => <CheckMarkCell active={(info.getValue<number>() === 1) as boolean} />,
    },
    {
      accessorKey: 'fileName',
      header: t('File Name'),
      size: 200,
      cell: (info) => (
        <TextCell className="truncate" title={info.getValue() as string}>
          {info.getValue<string>()}
        </TextCell>
      ),
    },
    {
      accessorKey: 'enableStat',
      header: t('Stats?'),
      size: 100,
      cell: (info) => <StatusCell label={info.getValue() as string} type="neutral" />,
    },
    {
      accessorKey: 'createdDt',
      header: t('Created'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'modifiedDt',
      header: t('Modified'),
      size: 180,
      cell: (info) => <TextCell>{info.getValue<string>()}</TextCell>,
    },
    {
      accessorKey: 'expires',
      header: t('Expires'),
      size: 180,
      cell: (info) => {
        const val = info.getValue() as number;
        if (val === 0) return <span className="text-gray-400">-</span>;
        return <TextCell>{val}</TextCell>;
      },
    },
    {
      id: 'tableActions',
      header: '',
      size: 120,
      minSize: 120,
      maxSize: 120,
      enableHiding: false,
      cell: ({ row }) => (
        <ActionsCell
          row={row}
          actions={[
            // Quick Actions
            {
              label: t('Edit'),
              icon: Edit,
              onClick: (data) => console.log('Edit', data.mediaId),
              isQuickAction: true,
              variant: 'primary',
            },
            {
              label: t('Download'),
              icon: Download,
              onClick: (data) => console.log('Download', data.mediaId),
              isQuickAction: true,
            },

            // Dropdown Menu Actions
            {
              label: t('Edit'),
              icon: Edit,
              onClick: (data) => console.log('Edit', data.mediaId),
            },
            {
              label: t('Make a Copy'),
              icon: CopyCheck,
              onClick: (data) => console.log('Make a Copy', data.mediaId),
            },
            {
              label: t('Move'),
              icon: FolderInput,
              onClick: (data) => console.log('Move', data.mediaId),
            },
            {
              label: t('Share'),
              icon: UserPlus2,
              onClick: (data) => console.log('Share', data.mediaId),
            },
            {
              label: t('Download'),
              icon: Download,
              onClick: (data) => console.log('Download', data.mediaId),
            },
            {
              label: t('Schedule'),
              icon: CalendarClock,
              onClick: (data) => console.log('Schedule', data.mediaId),
            },
            {
              label: t('Details'),
              icon: Info,
              onClick: (data) => console.log('Details', data.mediaId),
            },
            { isSeparator: true },
            {
              label: t('Enable Stats Collection'),
              onClick: (data) => console.log('Enable Stats', data.mediaId),
            },
            {
              label: t('Usage Report'),
              onClick: (data) => console.log('Usage Report', data.mediaId),
            },
            { isSeparator: true },
            {
              label: t('Delete'),
              icon: Trash2,
              onClick: (data) => handleDelete(data.mediaId),
              variant: 'danger',
            },
          ]}
        />
      ),
    },
  ];

  return (
    <section className="space-y-4 flex-1 flex flex-col min-h-0">
      {/* TODO: Navigation tabs & Buttons */}
      <MediaTopbar activeTab={activeTab} onTabClick={setActiveTab} navigation={MEDIA_NAV} />

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
        <div className="flex items-center gap-2 md:w-auto w-full">
          {/* Search */}
          <div className="relative flex-1 flex">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-4 h-4 text-gray-400" />
            </div>
            <input
              name="search"
              value={globalFilter}
              onChange={(e) => setGlobalFilter(e.target.value)}
              placeholder={t('Search media...')}
              className="py-2 px-3 pl-10 block h-[45px] bg-gray-100 rounded-lg md:w-[365px] w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
            />
          </div>
          {/* Inline filter button */}
          <Button
            leftIcon={!openFilter ? Filter : FilterX}
            variant="secondary"
            onClick={() => setOpenFilter((prev) => !prev)}
            removeTextOnMobile
          >
            {t('Filters')}
          </Button>
        </div>
      </div>
      {/* Filter Input Fields */}
      <FilterInputs onChange={handleFilterChange} open={openFilter} values={filterInputs} />
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
        onRefresh={handleRefresh}
        columnPinning={{
          left: ['tableSelection'],
          right: ['tableActions'],
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
        bulkActions={bulkActions}
      />
    </section>
  );
}
