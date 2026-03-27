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

import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { RowSelectionState } from '@tanstack/react-table';
import { Eye, Plus, Search, Slash, Trash2 } from 'lucide-react';
import { useState, useTransition, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams, useNavigate } from 'react-router-dom';

import { getColumnDefinitions } from './DatasetColumnsConfig';
import { DatasetColumnModals } from './components/DatasetColumnsModals';
import { useDatasetColumnsData } from './hooks/useDatasetColumnsData';

import Button from '@/components/ui/Button';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { createDatasetColumn, deleteDatasetColumn, getDatasetById } from '@/services/datasetApi';
import type { DatasetColumn } from '@/types/datasetColumn';

type ColumnModalType = 'edit' | 'delete' | 'copy' | null;

export default function DatasetColumns() {
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
    setGlobalFilter,
    isHydrated,
  } = useTableState<Record<string, string>>(`dataset_columns_${datasetId}`, {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      heading: true,
      dataTypeId: true,
      dataSetColumnTypeId: true,
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
  const [selectionCache, setSelectionCache] = useState<Record<string, DatasetColumn>>({});
  const [columnList, setColumnList] = useState<DatasetColumn[]>([]);
  const [itemsToDelete, setItemsToDelete] = useState<DatasetColumn[]>([]);

  const [activeModal, setActiveModal] = useState<ColumnModalType>(null);
  const [selectedColumn, setSelectedColumn] = useState<DatasetColumn | null>(null);

  const [isCloning, startCloneTransition] = useTransition();
  const [isDeleting, startDeleteTransition] = useTransition();
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const closeModal = () => {
    setActiveModal(null);
    setSelectedColumn(null);
    setItemsToDelete([]);
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['datasetColumns', datasetId] });
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
  } = useDatasetColumnsData({
    datasetId: datasetId!,
    pagination,
    sorting,
    enabled: isHydrated,
  });

  useEffect(() => {
    setColumnList(queryData?.rows ?? []);
  }, [queryData?.rows]);

  useEffect(() => {
    if (!columnList || columnList.length === 0) {
      return;
    }

    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;

      columnList.forEach((item) => {
        const id = item.dataSetColumnId.toString();
        if (rowSelection[id]) {
          if (!next[id]) {
            next[id] = item;
            hasChanges = true;
          }
        }
      });

      return hasChanges ? next : prev;
    });
  }, [columnList, rowSelection]);

  const handleAdd = () => {
    setSelectedColumn(null);
    setActiveModal('edit');
  };

  const handleEdit = (column: DatasetColumn) => {
    setSelectedColumn(column);
    setActiveModal('edit');
  };

  const handleDelete = (id: number) => {
    const column = columnList.find((c) => c.dataSetColumnId === id);
    if (!column) {
      return;
    }

    setItemsToDelete([column]);
    setDeleteError(null);
    setActiveModal('delete');
  };

  const handleCopyModalOpen = (column: DatasetColumn) => {
    setSelectedColumn(column);
    setActiveModal('copy');
  };

  const handleConfirmCopy = (newHeading: string) => {
    if (!selectedColumn) {
      return;
    }

    startCloneTransition(async () => {
      try {
        const copiedPayload = {
          heading: newHeading,
          dataSetColumnTypeId: selectedColumn.dataSetColumnTypeId ?? 1,
          dataTypeId: selectedColumn.dataTypeId ?? 1,
          listContent: selectedColumn.listContent ?? '',
          remoteField: selectedColumn.remoteField ?? '',
          columnOrder: selectedColumn.columnOrder ?? 0,
          tooltip: selectedColumn.tooltip ?? '',
          formula: selectedColumn.formula ?? '',
          showFilter: Boolean(selectedColumn.showFilter),
          dateFormat: selectedColumn.dateFormat ?? '',
          showSort: Boolean(selectedColumn.showSort),
          isRequired: Boolean(selectedColumn.isRequired),
        };

        await createDatasetColumn(datasetId!, copiedPayload);

        closeModal();
        handleRefresh();
      } catch (err) {
        console.error('Failed to copy column:', err);
      }
    });
  };

  const confirmDelete = (items: DatasetColumn[]) => {
    setDeleteError(null);
    startDeleteTransition(async () => {
      try {
        for (const item of items) {
          await deleteDatasetColumn(datasetId!, item.dataSetColumnId);
        }

        const newSelection = { ...rowSelection };
        items.forEach((item) => delete newSelection[item.dataSetColumnId.toString()]);
        setRowSelection(newSelection);

        closeModal();
        handleRefresh();
      } catch (err: unknown) {
        const apiError = err as { response?: { data?: { message?: string } } };
        setDeleteError(apiError.response?.data?.message || t('Failed to delete columns.'));
      }
    });
  };

  const columns = getColumnDefinitions({
    t,
    onEdit: handleEdit,
    onDelete: handleDelete,
    onCopy: handleCopyModalOpen,
  });

  const getAllSelectedItems = (): DatasetColumn[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is DatasetColumn => !!item);
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

  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const existingNames = columnList.map((col) => col.heading);

  const getRowId = (row: DatasetColumn) => {
    return row.dataSetColumnId.toString();
  };

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
              {t('Add Column')}
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
                {t('Edit Columns')}
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
                placeholder={t('Search columns...')}
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
              data={columnList}
              pageCount={pageCount}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={rowSelection}
              onRowSelectionChange={setRowSelection}
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

      <DatasetColumnModals
        datasetId={datasetId!}
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          setColumnList,
          deleteError,
          isDeleting,
          isCloning,
        }}
        selection={{
          selectedColumn,
          columnToDeleteId: itemsToDelete[0]?.dataSetColumnId || null,
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
