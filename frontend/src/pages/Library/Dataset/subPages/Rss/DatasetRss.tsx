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

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { RowSelectionState } from '@tanstack/react-table';
import { Eye, Plus, Search, Slash, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams, useNavigate } from 'react-router-dom';

import { getRssDefinitions } from './DatasetRssConfig';
import { DatasetRssModals } from './components/DatasetRssModals';
import { useDatasetRssData } from './hooks/useDatasetRssData';

import Button from '@/components/ui/Button';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { createDatasetRss, deleteDatasetRss, getDatasetById } from '@/services/datasetApi';
import type { DatasetRss } from '@/types/datasetRss';

type RssModalType = 'edit' | 'delete' | 'copy' | null;

export default function DatasetRss() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { datasetId } = useParams<{ datasetId: string }>();

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    globalFilter,
    debouncedFilter,
    setGlobalFilter,
    isHydrated,
  } = useTableState<Record<string, string>>(`dataset_rss_${datasetId}`, {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      heading: true,
      dataTypeId: true,
      dataSetRssTypeId: true,
      listContent: true,
      tooltip: true,
      columnOrder: true,
      isRequired: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: {},
    folderId: null,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, DatasetRss>>({});
  const [itemsToDelete, setItemsToDelete] = useState<DatasetRss[]>([]);

  const [activeModal, setActiveModal] = useState<RssModalType>(null);
  const [selectedRss, setSelectedRss] = useState<DatasetRss | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const closeModal = () => {
    setActiveModal(null);
    setSelectedRss(null);
    setItemsToDelete([]);
    setDeleteError(null);
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['datasetRss', datasetId] });
  };

  const { data: dataset } = useQuery({
    queryKey: ['dataset', datasetId],
    queryFn: () => getDatasetById(datasetId!),
    enabled: !!datasetId,
  });

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useDatasetRssData({
    datasetId: datasetId!,
    pagination,
    sorting,
    filter: debouncedFilter,
    enabled: isHydrated,
  });

  const rssList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const existingNames = rssList.map((rss) => rss.title);

  const getRowId = (row: DatasetRss) => {
    return row.id.toString();
  };

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      rssList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const copyMutation = useMutation({
    mutationFn: async (payload: Omit<DatasetRss, 'id' | 'dataSetId'>) => {
      return await createDatasetRss(datasetId!, payload);
    },
    onSuccess: () => {
      closeModal();
      handleRefresh();
    },
    onError: (err) => {
      console.error('Failed to copy RSS:', err);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (items: DatasetRss[]) => {
      for (const item of items) {
        await deleteDatasetRss(datasetId!, item.id);
      }
      return items;
    },
    onSuccess: (deletedItems) => {
      const newSelection = { ...rowSelection };
      deletedItems.forEach((item) => delete newSelection[item.id.toString()]);
      setRowSelection(newSelection);

      closeModal();
      handleRefresh();
    },
    onError: (err: unknown) => {
      const apiError = err as { response?: { data?: { message?: string } } };
      setDeleteError(apiError.response?.data?.message || t('Failed to delete RSS.'));
    },
  });

  const handleAdd = () => {
    setSelectedRss(null);
    setActiveModal('edit');
  };

  const handleEdit = (column: DatasetRss) => {
    setSelectedRss(column);
    setActiveModal('edit');
  };

  const handleDelete = (id: number) => {
    const rss = rssList.find((c) => c.id === id);
    if (!rss) {
      return;
    }

    setItemsToDelete([rss]);
    setDeleteError(null);
    setActiveModal('delete');
  };

  const handleCopyModalOpen = (column: DatasetRss) => {
    setSelectedRss(column);
    setActiveModal('copy');
  };

  const handleConfirmCopy = () => {
    if (!selectedRss) {
      return;
    }

    const copiedPayload: Omit<DatasetRss, 'id' | 'dataSetId'> = {
      title: selectedRss.title,
      author: selectedRss.author,
      titleColumnId: selectedRss.titleColumnId,
      summaryColumnId: selectedRss.summaryColumnId,
      contentColumnId: selectedRss.contentColumnId,
      publishedDateColumnId: selectedRss.publishedDateColumnId,
      sort: selectedRss.sort,
      filter: selectedRss.filter,
    };

    copyMutation.mutate(copiedPayload);
  };

  const confirmDelete = (items: DatasetRss[]) => {
    setDeleteError(null);
    deleteMutation.mutate(items);
  };

  const columns = getRssDefinitions({
    t,
    onEdit: handleEdit,
    onDelete: handleDelete,
    onCopy: handleCopyModalOpen,
  });

  const getAllSelectedItems = (): DatasetRss[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is DatasetRss => !!item);
  };

  const bulkActions = [
    {
      label: t('Delete'),
      icon: Trash2,
      onClick: () => {
        const allItems = getAllSelectedItems();
        setItemsToDelete(allItems);
        setDeleteError(null);
        setActiveModal('delete');
      },
      variant: 'danger' as const,
    },
  ];

  const libraryTabs = useFilteredTabs('library');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Datasets" navigation={libraryTabs} />
          <div className="flex items-center gap-2">
            <Button
              variant="primary"
              className="font-semibold"
              disabled={!isHydrated}
              onClick={handleAdd}
              leftIcon={Plus}
            >
              {t('Add Rss')}
            </Button>
            <Button
              variant="secondary"
              className="font-semibold"
              disabled={!isHydrated}
              onClick={() => {
                navigate(`/library/datasets/${datasetId}/data`);
              }}
              leftIcon={Eye}
            >
              {t('View Data')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-end items-center gap-4">
          <div className="w-full lg:flex-1 md:min-w-0">
            <nav className="flex items-center gap-1 text-sm font-medium text-gray-500">
              <button
                className="px-3 py-2 hover:text-gray-900 transition-colors cursor-pointer"
                onClick={() => navigate('/library/datasets')}
              >
                {t('Datasets')}
              </button>
              <Slash size={24} className="p-1 text-gray-400" />
              <span
                className="px-3 py-2 text-xibo-blue-500 text-sm font-semibold truncate max-w-xs"
                title={dataset?.dataSet}
              >
                {dataset?.dataSet ? dataset.dataSet : `${t('Dataset')} #${datasetId}`} -{' '}
                {t('Edit RSS')}
              </span>
            </nav>
          </div>
          <div className="flex items-center gap-2 w-full xl:w-115 lg:w-75 shrink-0">
            <div className="relative flex-1 flex">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                value={globalFilter}
                disabled={!isHydrated}
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search RSS...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
              />
            </div>
          </div>
        </div>

        {error && (
          <div
            className="bg-red-50 border border-red-200 text-red-800 p-4 mb-4 rounded-lg"
            role="alert"
          >
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={rssList}
              pageCount={pageCount}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={rowSelection}
              onRowSelectionChange={handleRowSelectionChange}
              bulkActions={bulkActions}
              onRefresh={handleRefresh}
              columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              viewMode={null}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <DatasetRssModals
        datasetId={datasetId!}
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting: deleteMutation.isPending,
          isCloning: copyMutation.isPending,
        }}
        selection={{
          selectedRss,
          rssToDeleteId: itemsToDelete[0] ? itemsToDelete[0].id : null,
          itemsToDelete,
          existingNames,
        }}
        handlers={{
          handleConfirmCopy,
          confirmDelete,
        }}
      />
    </section>
  );
}
