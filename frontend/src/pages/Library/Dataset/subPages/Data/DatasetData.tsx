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
import { Plus, Search, Slash, Table, Filter, FilterX } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams, useNavigate } from 'react-router-dom';

import { useDatasetColumnsData } from '../Columns/hooks/useDatasetColumnsData';

import { getDynamicDataColumns, getBulkActions } from './DatasetDataConfig';
import { DatasetDataModals } from './components/DatasetDataModals';
import { useDatasetData } from './hooks/useDatasetData';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { DatasetRowValue } from '@/services/datasetApi';
import {
  createDatasetRow,
  deleteDatasetRow,
  getDatasetById,
  type DynamicRowData,
} from '@/services/datasetApi';

type DataModalType = 'edit' | 'delete' | 'copy' | null;

export default function DatasetData() {
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
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<Record<string, string>>(`dataset_data_${datasetId}`, {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {},
    viewMode: 'table',
    globalFilter: '',
    filterInputs: {},
    folderId: null,
  });

  const [openFilter, setOpenFilter] = useState(false);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, DynamicRowData>>({});
  const [itemsToDelete, setItemsToDelete] = useState<DynamicRowData[]>([]);

  const [activeModal, setActiveModal] = useState<DataModalType>(null);
  const [selectedRow, setSelectedRow] = useState<DynamicRowData | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const { data: dataset } = useQuery({
    queryKey: ['dataset', datasetId],
    queryFn: () => getDatasetById(datasetId!),
    enabled: !!datasetId,
  });

  const { data: columnsData, isFetching: isFetchingColumns } = useDatasetColumnsData({
    datasetId: datasetId!,
    pagination: { pageIndex: 0, pageSize: 100 },
    filter: '',
    sorting: [],
    enabled: isHydrated,
  });

  const activeColumnFilters = Object.fromEntries(
    Object.entries(filterInputs).filter(([, v]) => v !== ''),
  );

  const {
    data: queryData,
    isFetching: isFetchingData,
    isError,
    error: queryError,
  } = useDatasetData({
    datasetId: datasetId!,
    pagination,
    filter: debouncedFilter,
    sorting,
    columnFilters: Object.keys(activeColumnFilters).length > 0 ? activeColumnFilters : undefined,
    enabled: isHydrated && !!columnsData,
  });

  const columnsSchema = columnsData?.rows || [];
  const rowData = queryData?.rows || [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);

  const filterOptions = columnsSchema
    .filter((col) => col.showFilter)
    .sort((a, b) => (a.columnOrder || 0) - (b.columnOrder || 0))
    .map((col) => {
      const listOptions = col.listContent
        ? col.listContent
            .split(',')
            .map((v) => v.trim())
            .filter(Boolean)
        : [];

      if (listOptions.length > 0) {
        return {
          label: col.heading,
          name: col.heading,
          type: 'select' as const,
          options: listOptions.map((opt) => ({ label: opt, value: opt })),
        };
      }

      return {
        label: col.heading,
        name: col.heading,
        type: 'text' as const,
        placeholder: t('Filter by {{heading}}', { heading: col.heading }),
      };
    });

  const handleResetFilters = () => {
    setFilterInputs({});
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const isLoading = !isHydrated || isFetchingColumns;

  const getRowId = (row: DynamicRowData) => {
    const id = row.id ?? row.datasetDataId;
    return id !== undefined && id !== null ? String(id) : '';
  };

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      rowData.forEach((item) => {
        const id = getRowId(item);
        if (id && newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const closeModal = () => {
    setActiveModal(null);
    setSelectedRow(null);
    setItemsToDelete([]);
    setDeleteError(null);
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['datasetData', datasetId] });
  };

  const copyMutation = useMutation({
    mutationFn: async (rowToCopy: Record<string, DatasetRowValue>) => {
      return await createDatasetRow(datasetId!, rowToCopy);
    },
    onSuccess: () => {
      closeModal();
      handleRefresh();
    },
    onError: (err) => {
      console.error('Failed to duplicate row:', err);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (items: DynamicRowData[]) => {
      for (const item of items) {
        const rowId = getRowId(item);
        if (rowId) await deleteDatasetRow(datasetId!, rowId);
      }
      return items;
    },
    onSuccess: (deletedItems) => {
      const newSelection = { ...rowSelection };
      deletedItems.forEach((item) => {
        const rowId = getRowId(item);
        if (rowId) delete newSelection[rowId];
      });
      setRowSelection(newSelection);

      closeModal();
      handleRefresh();
    },
    onError: (err: unknown) => {
      const apiError = err as { response?: { data?: { message?: string } } };
      setDeleteError(apiError.response?.data?.message || t('Failed to delete rows.'));
    },
  });

  const handleConfirmCopy = () => {
    if (!selectedRow) return;

    const rowToCopy: Record<string, DatasetRowValue> = {};
    columnsSchema.forEach((col) => {
      if (col.dataSetColumnTypeId === 1) {
        const columnId = String(col.dataSetColumnId);
        rowToCopy[columnId] = selectedRow[col.heading] ?? selectedRow[columnId] ?? '';
      }
    });

    copyMutation.mutate(rowToCopy);
  };

  const confirmDelete = (items: DynamicRowData[]) => {
    deleteMutation.mutate(items);
  };

  const tableColumns = getDynamicDataColumns(columnsSchema, {
    t,
    rowIdKey: 'id',
    onEdit: (row) => {
      setSelectedRow(row);
      setActiveModal('edit');
    },
    onCopy: (row) => {
      setSelectedRow(row);
      setActiveModal('copy');
    },
    onDelete: (id) => {
      const row = rowData.find((r) => getRowId(r) === String(id));
      if (row) {
        setItemsToDelete([row]);
        setDeleteError(null);
        setActiveModal('delete');
      }
    },
  });

  const getAllSelectedItems = (): DynamicRowData[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is DynamicRowData => !!item);
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      setActiveModal('delete');
    },
  });

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
              disabled={isLoading || columnsSchema.length === 0}
              onClick={() => {
                setSelectedRow(null);
                setActiveModal('edit');
              }}
              leftIcon={Plus}
            >
              {t('Add Row')}
            </Button>
            <Button
              variant="secondary"
              className="font-semibold"
              onClick={() => navigate(`/library/datasets/${datasetId}/column`)}
              leftIcon={Table}
            >
              {t('View Columns')}
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
              <span className="px-3 py-2 text-xibo-blue-500 text-sm font-semibold truncate max-w-xs">
                {dataset?.dataSet ? dataset.dataSet : `${t('Dataset')} #${datasetId}`} -{' '}
                {t('View Data')}
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
                disabled={isLoading}
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search data...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50"
              />
            </div>
            {filterOptions.length > 0 && (
              <Button
                leftIcon={!openFilter ? Filter : FilterX}
                variant="secondary"
                onClick={() => setOpenFilter((prev) => !prev)}
                removeTextOnMobile
              >
                {t('Filters')}
              </Button>
            )}
          </div>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({ ...prev, [name]: value as string }));
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        {error && (
          <div
            className="bg-red-50 border border-red-200 text-red-800 p-4 mb-4 rounded-lg"
            role="alert"
          >
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
          {isLoading ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading Schema...')}</span>
            </div>
          ) : columnsSchema.length === 0 ? (
            <div className="flex-1 flex flex-col items-center justify-center bg-gray-50 rounded-lg border border-gray-200 p-8 text-center">
              <p className="text-gray-500 mb-4">
                {t('This dataset has no columns configured yet.')}
              </p>
              <Button
                variant="primary"
                onClick={() => navigate(`/library/datasets/${datasetId}/column`)}
              >
                {t('Configure Columns')}
              </Button>
            </div>
          ) : (
            <DataTable
              columns={tableColumns}
              data={rowData}
              pageCount={pageCount}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetchingData}
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

      <DatasetDataModals
        datasetId={datasetId!}
        columnsSchema={columnsSchema}
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          isCloning: copyMutation.isPending,
          isDeleting: deleteMutation.isPending,
          deleteError,
        }}
        selection={{
          selectedData: selectedRow,
          itemsToDelete,
          rowToDeleteId: itemsToDelete[0] ? getRowId(itemsToDelete[0]) : null,
        }}
        handlers={{
          handleConfirmCopy,
          confirmDelete,
        }}
      />
    </section>
  );
}
