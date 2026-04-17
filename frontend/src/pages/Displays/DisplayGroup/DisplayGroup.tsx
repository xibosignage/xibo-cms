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
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  getBulkActions,
  getDisplayGroupColumns,
  INITIAL_FILTER_STATE,
  type DisplayGroupFilterInput,
  type ModalType,
} from './DisplayGroupConfig';
import { DisplayGroupModals } from './components/DisplayGroupModals';
import { useDisplayGroupActions } from './hooks/useDisplayGroupActions';
import { useDisplayGroupData } from './hooks/useDisplayGroupData';
import { useDisplayGroupFilterOptions } from './hooks/useDisplayGroupFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import FolderSidebar from '@/components/ui/FolderSidebar';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useFolderActions } from '@/hooks/useFolderActions';
import { usePermissions } from '@/hooks/usePermissions';
import { useTableState } from '@/hooks/useTableState';
import type { DisplayGroup } from '@/types/displayGroup';

export default function DisplayGroupPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { user } = useUserContext();
  const canViewFolders = usePermissions()?.canViewFolders;
  const homeFolderId = user?.homeFolderId ?? 1;

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
    folderId: selectedFolderId,
    setFolderId: setSelectedFolderId,
    isHydrated,
  } = useTableState<DisplayGroupFilterInput>('displayGroup_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      displayGroupId: true,
      displayGroup: true,
      description: true,
      isDynamic: true,
      dynamicCriteria: true,
      dynamicCriteriaTags: true,
      tags: true,
      groupsWithPermissions: false,
      ref1: false,
      ref2: false,
      ref3: false,
      ref4: false,
      ref5: false,
      createdDt: false,
      modifiedDt: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
    folderId: canViewFolders ? homeFolderId : null,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [openFilter, setOpenFilter] = useState(false);
  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [selectedFolderName, setSelectedFolderName] = useState(t('Root Folder'));
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, DisplayGroup>>({});
  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<DisplayGroup[]>([]);
  const [itemsToMove, setItemsToMove] = useState<DisplayGroup[]>([]);
  const [selectedDisplayGroup, setSelectedDisplayGroup] = useState<DisplayGroup | null>(null);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useDisplayGroupData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    folderId: selectedFolderId,
    enabled: isHydrated,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const displayGroupList = data ?? [];

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['displayGroup'] });
  };

  const handleFolderChange = (folder: { id: number | null; text: string | '' }) => {
    setSelectedFolderId(folder.id);
    setSelectedFolderName(folder.text);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setRowSelection({});
  };

  const folderActions = useFolderActions({
    onSuccess: (targetFolder) => {
      setFolderRefreshTrigger((prev) => prev + 1);
      if (targetFolder) {
        handleFolderChange({ id: targetFolder.id, text: targetFolder.text });
      } else {
        handleRefresh();
      }
    },
  });

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const {
    isDeleting,
    deleteError,
    setDeleteError,
    isCopying,
    confirmCopy,
    isMoving,
    confirmDelete,
    confirmMove,
    confirmCollectNow,
    isActionPending,
    actionError,
    confirmSendCommand,
    confirmTriggerWebhook,
    confirmBulkSendCommand,
    confirmBulkTriggerWebhook,
  } = useDisplayGroupActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const getRowId = (row: DisplayGroup) => row.displayGroupId.toString();

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      displayGroupList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const existingNames = displayGroupList.map((g) => g.displayGroup).filter(Boolean);

  const getAllSelectedItems = (): DisplayGroup[] =>
    Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is DisplayGroup => !!item);

  const columns = getDisplayGroupColumns({
    t,
    onDelete: (displayGroup) => {
      setItemsToDelete([displayGroup]);
      setDeleteError(null);
      openModal('delete');
    },
    openEditModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('edit');
    },
    openCopyModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('copy');
    },
    openMembersModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('members');
    },
    openMoveModal: canViewFolders
      ? (displayGroup) => {
          setItemsToMove([displayGroup]);
          openModal('move');
        }
      : undefined,
    openScheduleModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('schedule');
    },
    openAssignFilesModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('assignFiles');
    },
    openAssignLayoutsModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('assignLayouts');
    },
    openShareModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('share');
    },
    openSendCommandModal: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('sendCommand');
    },
    collectNow: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('collectNow');
    },
    triggerWebhook: (displayGroup) => {
      setSelectedDisplayGroup(displayGroup);
      openModal('triggerWebhook');
    },
  });

  const { filterOptions } = useDisplayGroupFilterOptions(t);
  const libraryTabs = useFilteredTabs('displays');

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
    onMove: () => {
      const allItems = getAllSelectedItems();
      setItemsToMove(allItems);
      openModal('move');
    },
    onBulkSendCommand: () => {
      openModal('bulkSendCommand');
    },
    onBulkTriggerWebhook: () => {
      openModal('bulkTriggerWebhook');
    },
    onBulkShare: () => {
      const allItems = getAllSelectedItems();
      const ids = allItems.map((i) => i.displayGroupId);
      setShareEntityIds(ids);
      openModal('share');
    },
  });

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <FolderSidebar
        isOpen={showFolderSidebar}
        selectedFolderId={selectedFolderId}
        onSelect={handleFolderChange}
        onClose={() => setShowFolderSidebar(false)}
        onAction={folderActions.openAction}
        refreshTrigger={folderRefreshTrigger}
      />
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Display Groups" navigation={libraryTabs} />
          <Button
            leftIcon={Plus}
            disabled={!isHydrated}
            onClick={() => openModal('add')}
            removeTextOnMobile
          >
            {t('Add Display Group')}
          </Button>
        </div>

        <div className="flex flex-col lg:flex-row justify-between items-center gap-4">
          <div className="w-full lg:flex-1 md:min-w-0">
            <FolderBreadcrumb
              currentFolderId={selectedFolderId}
              onNavigate={handleFolderChange}
              isSidebarOpen={showFolderSidebar}
              onToggleSidebar={() => setShowFolderSidebar(!showFolderSidebar)}
              onAction={folderActions.openAction}
              refreshTrigger={folderRefreshTrigger}
            />
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
                placeholder={t('Search display groups...')}
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
            setFilterInputs(
              (prev) =>
                ({
                  ...prev,
                  [name]: value === undefined || value === '' ? null : value,
                }) as DisplayGroupFilterInput,
            );
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
              <span className="text-gray-400 font-medium">{t('Loading display groups...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={displayGroupList}
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
      <DisplayGroupModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
          isCopying,
          isMoving,
          isActionPending,
          actionError,
        }}
        selection={{
          selectedDisplayGroup,
          itemsToDelete,
          existingNames,
          itemsToMove,
          folderName: selectedFolderName,
          shareEntityIds,
          setShareEntityIds,
        }}
        handlers={{
          confirmDelete,
          confirmCopy,
          confirmMove: (targetFolderId) => confirmMove(itemsToMove, targetFolderId),
          confirmCollectNow: () => {
            if (selectedDisplayGroup) {
              confirmCollectNow(selectedDisplayGroup.displayGroupId);
            }
          },
          confirmSendCommand: (displayGroupId, commandId) =>
            confirmSendCommand(displayGroupId, commandId),
          confirmTriggerWebhook: (displayGroupId, triggerCode) =>
            confirmTriggerWebhook(displayGroupId, triggerCode),
          confirmBulkSendCommand: (items, commandId) => confirmBulkSendCommand(items, commandId),
          confirmBulkTriggerWebhook: (items, triggerCode) =>
            confirmBulkTriggerWebhook(items, triggerCode),
          getAllSelectedItems,
        }}
      />
    </section>
  );
}
