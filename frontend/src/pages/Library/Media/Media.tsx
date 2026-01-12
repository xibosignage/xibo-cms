import {
  type ColumnDef,
  type SortingState,
  type PaginationState,
  type OnChangeFn,
  type RowSelectionState,
} from '@tanstack/react-table';
import { useEffect, useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@/components/ui/DataTable';
import RowActions from '@/components/ui/RowActions';
import { useDebounce } from '@/hooks/useDebounce';
import { fetchMedia } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

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
              aria-label={t('Select all')}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
          </div>
        ),
        cell: ({ row }) => (
          <div className="px-1">
            <input
              type="checkbox"
              checked={row.getIsSelected()}
              onChange={(e) => row.toggleSelected(!!e.target.checked)}
              aria-label={t('Select row')}
              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
          </div>
        ),
        enableSorting: false,
        enableHiding: false,
        size: 50,
      },
      {
        accessorKey: 'mediaId',
        header: t('ID'),
        size: 80,
      },
      {
        accessorKey: 'name',
        header: t('Name'),
        size: 200,
      },
      {
        accessorKey: 'thumbnail',
        header: t('Thumbnail'),
        size: 100,
        cell: ({ row }) => {
          const thumb = row.original.thumbnail;
          const name = row.original.name;

          if (!thumb) {
            return (
              <div className="h-12 w-12 rounded-md bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
                N/A
              </div>
            );
          }

          return (
            <div className="h-12 w-12 overflow-hidden rounded-md border bg-white">
              <img
                src={thumb}
                alt={name ?? 'thumbnail'}
                className="h-full w-full object-cover"
                loading="lazy"
                onError={(e) => (e.currentTarget.style.visibility = 'hidden')}
              />
            </div>
          );
        },
      },
      {
        accessorKey: 'mediaType',
        header: t('Type'),
        size: 100,
      },
      {
        accessorKey: 'owner',
        header: t('Owner'),
        size: 150,
      },
      {
        accessorKey: 'valid',
        header: t('Valid'),
        size: 80,
        cell: ({ getValue }) => {
          const isValid = getValue<boolean>();
          return isValid ? (
            <span className="text-green-600 font-bold" aria-label={t('Valid')}>
              ✔
            </span>
          ) : (
            <span className="text-red-600 font-bold" aria-label={t('Invalid')}>
              ✘
            </span>
          );
        },
      },
      {
        accessorKey: 'createdDt',
        header: t('Created'),
        size: 150,
      },
      {
        id: 'actions',
        size: 60,
        cell: ({ row }) => (
          <RowActions
            row={row.original}
            onEdit={(media) => {
              console.log('Edit media:', media.mediaId);
              // Implement edit logic or navigation here
            }}
            onDelete={(media) => {
              if (confirm(t('Are you sure you want to delete this media?', { name: media.name }))) {
                console.log('Delete media:', media.mediaId);
              }
            }}
          />
        ),
      },
    ],
    [t],
  );

  const handleDelete = () => {
    const selectedItems = Object.keys(rowSelection)
      .map((index) => data[Number(index)])
      .filter((item): item is MediaRow => item !== undefined);

    if (selectedItems.length === 0) {
      return;
    }

    const itemList = selectedItems.map((item) => `• ${item.name} (ID: ${item.mediaId})`).join('\n');

    const message = `${t('Are you sure you want to delete these items?')}\n\n${itemList}`;

    if (window.confirm(message)) {
      console.log(
        'Deleting items:',
        selectedItems.map((i) => i.mediaId),
      );

      // Clear selection
      setRowSelection({});
    }
  };

  return (
    <section className="space-y-4 p-4">
      {error && (
        <div className="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
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
          left: ['select', 'mediaId'],
          right: ['actions'],
        }}
        selectionActions={
          <button
            onClick={handleDelete}
            className="text-xs bg-white border border-blue-300 text-blue-700 hover:bg-blue-50 px-3 py-1.5 rounded shadow-sm font-medium transition-colors"
          >
            {t('Delete Selected')}
          </button>
        }
      />
    </section>
  );
}
