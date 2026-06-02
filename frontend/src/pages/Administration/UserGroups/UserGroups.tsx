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
import { Filter, FilterX, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  getUserGroupColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type ModalType,
  type UserGroupFilterInput,
} from './UserGroupsConfig';
import { UserGroupModals } from './components/UserGroupModals';
import { useUserGroupActions } from './hooks/useUserGroupActions';
import { useUserGroupFilterOptions } from './hooks/useUserGroupFilterOptions';
import { useUserGroupsData } from './hooks/useUserGroupsData';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import type { UserGroup } from '@/types/userGroup';

export default function UserGroups() {
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
  } = useTableState<UserGroupFilterInput>('user_groups_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      groupId: true,
      group: true,
      description: true,
      libraryQuota: true,
      isShownForAddUser: true,
      isSystemNotification: false,
      isDisplayNotification: false,
      isDataSetNotification: false,
      isLayoutNotification: false,
      isLibraryNotification: false,
      isReportNotification: false,
      isScheduleNotification: false,
      isCustomNotification: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType>(null);
  const [selectedUserGroup, setSelectedUserGroup] = useState<UserGroup | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<UserGroup[]>([]);
  const [selectionCache, setSelectionCache] = useState<Record<string, UserGroup>>({});

  const openModal = (modal: ModalType, userGroup?: UserGroup) => {
    setSelectedUserGroup(userGroup ?? null);
    setActiveModal(modal);
  };

  const closeModal = () => {
    setActiveModal(null);
    setSelectedUserGroup(null);
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['userGroups'] });
  };

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useUserGroupsData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const userGroupList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const getRowId = (row: UserGroup) => row.groupId.toString();

  // Track selected rows across pages
  useEffect(() => {
    if (!userGroupList || userGroupList.length === 0) return;
    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;
      userGroupList.forEach((item) => {
        const id = item.groupId.toString();
        if (rowSelection[id] && next[id] !== item) {
          next[id] = item;
          hasChanges = true;
        }
      });
      return hasChanges ? next : prev;
    });
  }, [userGroupList, rowSelection]);

  const getAllSelectedItems = (): UserGroup[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is UserGroup => !!item);
  };

  const { isDeleting, deleteError, setDeleteError, confirmDelete } = useUserGroupActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getUserGroupColumns({
    t,
    onEdit: (userGroup) => openModal('edit', userGroup),
    onCopy: (userGroup) => openModal('copy', userGroup),
    onMembers: (userGroup) => openModal('members', userGroup),
    onDelete: (userGroup) => {
      setItemsToDelete([userGroup]);
      setDeleteError(null);
      openModal('delete', userGroup);
    },
  });

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
  });

  const { filterOptions } = useUserGroupFilterOptions(t);

  const administrationTabs = useFilteredTabs('administration');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="User Groups" navigation={administrationTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              onClick={() => openModal('add')}
              leftIcon={Plus}
            >
              {t('Add User Group')}
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
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search user groups...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
              />
            </div>
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

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({
              ...prev,
              [name]: value === undefined || value === '' ? null : value,
            }));
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
              <span className="text-gray-400 font-medium">{t('Loading your preferences...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={userGroupList}
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
              enableSelection
              columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              viewMode={null}
              getRowId={getRowId}
              bulkActions={bulkActions}
            />
          )}
        </div>
      </div>

      <UserGroupModals
        activeModal={activeModal}
        closeModal={closeModal}
        handleRefresh={handleRefresh}
        selectedUserGroup={selectedUserGroup}
        itemsToDelete={itemsToDelete}
        deleteError={deleteError}
        setDeleteError={setDeleteError}
        isDeleting={isDeleting}
        confirmDelete={confirmDelete}
      />
    </section>
  );
}
