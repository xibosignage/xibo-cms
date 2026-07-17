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
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { getBulkActions, getTagColumns, INITIAL_FILTER_STATE } from './TagsConfig';
import type { ModalType, TagFilterInput } from './TagsConfig';
import { TagModals } from './components/TagModals';
import { useTagFilterOptions } from './hooks/useTagFilterOptions';
import { useTagsActions } from './hooks/useTagsActions';
import { tagQueryKeys, useTagsData } from './hooks/useTagsData';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { Tag } from '@/types/tag';
import { countActiveFilters } from '@/utils/filters';

export default function Tags() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { user } = useUserContext();
  const isSuperAdmin = user?.userTypeId === 1;

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
  } = useTableState<TagFilterInput>('tag_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: { tag: true },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Tag>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<Tag[]>([]);
  const [selectedTag, setSelectedTag] = useState<Tag | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useTagsData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const tagList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const getRowId = (row: Tag) => String(row.tagId);

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      tagList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: tagQueryKeys.all });
  };

  const { isDeleting, deleteError, setDeleteError, confirmDelete } = useTagsActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleDelete = (tagId: number) => {
    const tag = tagList.find((t) => t.tagId === tagId);
    if (!tag) return;

    setItemsToDelete([tag]);
    setDeleteError(null);
    openModal('delete');
  };

  const handleEdit = (tag: Tag) => {
    setSelectedTag(tag);
    openModal('edit');
  };

  const handleUsage = (tag: Tag) => {
    setSelectedTag(tag);
    openModal('usage');
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const getAllSelectedItems = (): Tag[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Tag => !!item);
  };

  const columns = getTagColumns({
    t,
    onDelete: handleDelete,
    onEdit: handleEdit,
    onUsage: handleUsage,
    isSuperAdmin,
  });

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
    isSuperAdmin,
  });

  const { filterOptions } = useTagFilterOptions();
  const adminTabs = useFilteredTabs('administration');

  const activeFilterCount = countActiveFilters(filterInputs, INITIAL_FILTER_STATE, filterOptions);

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Tags" navigation={adminTabs} />
          <div className="flex items-center gap-2">
            {isSuperAdmin && (
              <Button
                variant="primary"
                className="font-semibold"
                disabled={!isHydrated}
                onClick={() => openModal('add')}
                leftIcon={Plus}
              >
                {t('Add Tag')}
              </Button>
            )}
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-end items-center gap-4 mb-4">
          <div className="flex items-center gap-2 w-full xl:w-115 lg:w-75 shrink-0">
            <div className="relative flex-1 flex">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                aria-label={t('Search tags')}
                value={globalFilter}
                disabled={!isHydrated}
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search tags...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
              />
            </div>
            <FilterButton
              isOpen={openFilter}
              onToggle={() => setOpenFilter((prev) => !prev)}
              activeCount={activeFilterCount}
              disabled={!isHydrated}
            />
          </div>
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({ ...prev, [name]: value }));
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
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
              <span className="text-gray-400 font-medium">{t('Loading...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={tagList}
              pageCount={pageCount}
              rowCount={queryData?.totalCount || 0}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={rowSelection}
              onRowSelectionChange={handleRowSelectionChange}
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

      <TagModals
        activeModal={activeModal}
        closeModal={closeModal}
        handleRefresh={handleRefresh}
        selectedTag={selectedTag}
        itemsToDelete={itemsToDelete}
        deleteError={deleteError}
        isDeleting={isDeleting}
        confirmDelete={confirmDelete}
      />
    </section>
  );
}
