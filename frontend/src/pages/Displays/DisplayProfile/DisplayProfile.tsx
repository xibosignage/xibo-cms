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

import { useQueryClient } from '@tanstack/react-query';
import type { RowSelectionState } from '@tanstack/react-table';
import { Search, Filter, FilterX, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './DisplayProfileConfig';
import {
  getDisplayProfileColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type DisplayProfileFilterInput,
} from './DisplayProfileConfig';
import { DisplayProfileModals } from './components/DisplayProfileModals';
import { useDisplayProfileActions } from './hooks/useDisplayProfileActions';
import { useDisplayProfileData } from './hooks/useDisplayProfileData';
import { useDisplayProfileFilterOptions } from './hooks/useDisplayProfileFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { DisplayProfile } from '@/types/displayProfile';

export default function DisplayProfile() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

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
  } = useTableState<DisplayProfileFilterInput>('displayProfile_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      displayProfileId: true,
      displayProfile: true,
      type: true,
      isDefault: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, DisplayProfile>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const [itemsToDelete, setItemsToDelete] = useState<DisplayProfile[]>([]);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);
  const [selectedDisplayProfileId, setSelectedDisplayProfileId] = useState<number | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useDisplayProfileData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [displayProfileList, setDisplayProfileList] = useState<DisplayProfile[]>([]);

  useEffect(() => {
    setDisplayProfileList(data ?? []);
  }, [data]);

  useEffect(() => {
    if (!displayProfileList || displayProfileList.length === 0) {
      return;
    }

    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;

      displayProfileList.forEach((item) => {
        const id = item.displayProfileId.toString();
        if (rowSelection[id] && next[id] !== item) {
          next[id] = item;
          hasChanges = true;
        }
      });

      return hasChanges ? next : prev;
    });
  }, [displayProfileList, rowSelection]);

  const selectedDisplayProfile =
    displayProfileList.find((m) => m.displayProfileId === selectedDisplayProfileId) ?? null;
  const existingNames = displayProfileList.map((m) => m.name);

  const getRowId = (row: DisplayProfile) => {
    return row.displayProfileId.toString();
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['displayProfile'] });
  };

  const { isDeleting, deleteError, setDeleteError, confirmDelete, isCopying, confirmCopy } =
    useDisplayProfileActions({
      t,
      handleRefresh,
      closeModal,
      setRowSelection,
    });

  const handleDelete = (id: number) => {
    const displayProfile = displayProfileList.find((m) => m.displayProfileId === id);
    if (!displayProfile) {
      return;
    }

    setItemsToDelete([displayProfile]);
    setDeleteError(null);
    openModal('delete');
  };

  const openAddModal = () => {
    setSelectedDisplayProfileId(null);
    openModal('add');
  };

  const openAddEditModal = (displayProfile: DisplayProfile | null) => {
    if (displayProfile) {
      setSelectedDisplayProfileId(displayProfile.displayProfileId);
      openModal('edit');
    } else {
      openAddModal();
    }
  };

  const openCopyModal = (displayProfile: DisplayProfile) => {
    setSelectedDisplayProfileId(displayProfile.displayProfileId);
    openModal('copy');
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getDisplayProfileColumns({
    t,
    onDelete: handleDelete,
    openEditModal: openAddEditModal,
    openCopyModal,
  });

  const getAllSelectedItems = (): DisplayProfile[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is DisplayProfile => !!item); // Filter out any missing data
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
  });

  const { filterOptions } = useDisplayProfileFilterOptions(t);

  const libraryTabs = useFilteredTabs('displays');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Display Settings" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              disabled={!isHydrated}
              onClick={openAddModal}
              leftIcon={Plus}
            >
              {t('Add Display Profile')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-end items-center gap-4">
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
                placeholder={t('Search display profiles...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
              />
            </div>
            <Button
              leftIcon={!openFilter ? Filter : FilterX}
              variant="secondary"
              disabled={!isHydrated}
              onClick={() => setOpenFilter((prev) => !prev)}
              removeTextOnMobile
            >
              {t('Filters')}
            </Button>
          </div>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => {
              return {
                ...prev,
                [name]: value === undefined || value === '' ? null : value,
              } as DisplayProfileFilterInput;
            });
            setPagination((prev) => {
              return { ...prev, pageIndex: 0 };
            });
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">
                {t('Loading your display settings...')}
              </span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={displayProfileList}
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
              onRefresh={handleRefresh}
              columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={bulkActions}
              viewMode={null}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <DisplayProfileModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          setDisplayProfileList,
          deleteError,
          isDeleting,
          isCopying,
        }}
        selection={{
          selectedDisplayProfile,
          selectedDisplayProfileId,
          itemsToDelete,
          existingNames,
          shareEntityIds,
          setShareEntityIds,
        }}
        handlers={{
          confirmDelete,
          confirmCopy,
        }}
      />
    </section>
  );
}
