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
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './DisplaysConfig';
import {
  getDisplayColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type DisplayFilterInput,
} from './DisplaysConfig';
import DisplayMap from './components/DisplayMap';
import { DisplayModals } from './components/DisplaysModals';
import { useDisplaysActions } from './hooks/useDisplaysActions';
import { useDisplaysData } from './hooks/useDisplaysData';
import { useDisplaysFilterOptions } from './hooks/useDisplaysFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import FolderSidebar from '@/components/ui/FolderSidebar';
import TabNav from '@/components/ui/TabNav';
import { DataMap } from '@/components/ui/table/DataMap';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useFolderActions } from '@/hooks/useFolderActions';
import { usePermissions } from '@/hooks/usePermissions';
import { useTableState } from '@/hooks/useTableState';
import type { Display } from '@/types/display';

export default function Displays() {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const queryClient = useQueryClient();
  const canViewFolders = usePermissions()?.canViewFolders;
  const homeFolderId = user?.homeFolderId ?? 1;

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    viewMode,
    setViewMode,
    globalFilter,
    debouncedFilter,
    setGlobalFilter,
    filterInputs,
    setFilterInputs,
    folderId: selectedFolderId,
    setFolderId: setSelectedFolderId,
    isHydrated,
  } = useTableState<DisplayFilterInput>('displays_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      displayId: true,
      display: true,
      mediaInventoryStatus: true,
      clientType: true,
      clientAddress: true,
      licensed: true,
      loggedIn: true,
      currentLayout: true,
      deviceName: false,
      address: false,
      storageAvailableSpace: false,
      storageTotalSpace: false,
      storageFree: false,
      description: false,
      orientation: false,
      resolution: false,
      tags: false,
      defaultLayout: false,
      incSchedule: false,
      emailAlert: false,
      lastAccessed: false,
      displayProfile: false,
      clientVersion: false,
      isPlayerSupported: false,
      macAddress: false,
      timeZone: false,
      languages: false,
      latitude: false,
      longitude: false,
      screenShotRequested: false,
      thumbnail: false,
      cmsTransfer: false,
      bandwidthLimit: false,
      lastCommandSuccess: false,
      xmrRegistered: false,
      commercialLicence: false,
      remote: false,
      groupsWithPermissions: false,
      screenSize: false,
      isMobile: false,
      isOutdoor: false,
      ref1: false,
      ref2: false,
      ref3: false,
      ref4: false,
      ref5: false,
      customId: false,
      costPerPlay: false,
      impressionsPerPlay: false,
      createdDt: false,
      modifiedDt: false,
      countFaults: false,
      osVersion: false,
      osSdk: false,
      manufacturer: false,
      brand: false,
      model: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
    folderId: canViewFolders ? homeFolderId : null,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Display>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<Display[]>([]);
  const [itemsToMove, setItemsToMove] = useState<Display[]>([]);
  const [bulkActionItems, setBulkActionItems] = useState<Display[]>([]);
  const [selectedDisplayId, setSelectedDisplayId] = useState<number | null>(null);
  const [actionDisplay, setActionDisplay] = useState<Display | null>(null);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const handleFolderChange = (folder: { id: number | null; text: string | '' }) => {
    setSelectedFolderId(folder.id);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setRowSelection({});
  };

  const folderActions = useFolderActions({
    onSuccess: (targetFolder) => {
      setFolderRefreshTrigger((prev) => prev + 1);
      if (targetFolder?.id === -1) {
        targetFolder.id = homeFolderId;
      }
      if (targetFolder) {
        handleFolderChange({ id: targetFolder.id, text: targetFolder.text });
      } else {
        handleRefresh();
      }
    },
  });

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useDisplaysData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    folderId: canViewFolders ? selectedFolderId : null,
    enabled: isHydrated,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const displayList = data ?? [];

  const getRowId = (row: Display) => row.displayId.toString();

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      displayList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const selectedDisplay = displayList.find((d) => d.displayId === selectedDisplayId) ?? null;

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['display'] });
  };

  const {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    confirmAuthorise,
    handleConfirmMove,
    handleManage,
    isActionPending,
    actionError,
    setActionError,
    confirmCheckLicence,
    confirmRequestScreenShot,
    confirmCollectNow,
    confirmWakeOnLan,
    confirmPurgeAll,
    confirmTriggerWebhook,
    confirmSetDefaultLayout,
    confirmMoveCms,
    confirmMoveCmsCancel,
    confirmSetBandwidth,
    confirmBulkAuthorise,
    confirmBulkCheckLicence,
    confirmBulkRequestScreenShot,
    confirmBulkCollectNow,
    confirmBulkTriggerWebhook,
    confirmBulkSetDefaultLayout,
    confirmSendCommand,
    confirmBulkMoveCms,
    handleJumpToScheduledLayouts,
  } = useDisplaysActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const openActionModal = (display: Display, modal: ModalType) => {
    setActionDisplay(display);
    setActionError(null);
    openModal(modal);
  };

  const handleDelete = (id: number) => {
    const display = displayList.find((d) => d.displayId === id);
    if (!display) {
      return;
    }
    setItemsToDelete([display]);
    setDeleteError(null);
    openModal('delete');
  };

  const openEditModal = (display: Display) => {
    setSelectedDisplayId(display.displayId);
    openModal('edit');
  };

  const openShareModal = (displayGroupId: number) => {
    setShareEntityIds(displayGroupId);
    openModal('share');
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getDisplayColumns({
    t,
    onDelete: handleDelete,
    openEditModal,
    openMoveModal: canViewFolders
      ? (display) => {
          setItemsToMove([display] as Display[]);
          openModal('move');
        }
      : undefined,
    openShareModal,
    onAuthorise: (display) => openActionModal(display, 'authorise'),
    onManage: handleManage,
    onCheckLicence: (display) => openActionModal(display, 'checkLicence'),
    onRequestScreenShot: (display) => openActionModal(display, 'requestScreenShot'),
    onCollectNow: (display) => openActionModal(display, 'collectNow'),
    onWakeOnLan: (display) => openActionModal(display, 'wakeOnLan'),
    onPurgeAll: (display) => openActionModal(display, 'purgeAll'),
    onTriggerWebhook: (display) => openActionModal(display, 'triggerWebhook'),
    onSetDefaultLayout: (display) => openActionModal(display, 'defaultLayout'),
    onMoveCms: (display) => openActionModal(display, 'moveCms'),
    onMoveCmsCancel: (display) => openActionModal(display, 'moveCmsCancel'),
    onAddToGroup: (display) => openActionModal(display, 'manageGroups'),
    onAssignLayouts: (display) => openActionModal(display, 'assignLayout'),
    onAssignFiles: (display) => openActionModal(display, 'assignMedia'),
    onSendCommand: (display) => openActionModal(display, 'sendCommand'),
    onJumpToScheduledLayouts: handleJumpToScheduledLayouts,
  });

  const getAllSelectedItems = (): Display[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Display => !!item);
  };

  const openBulkModal = (modal: ModalType) => {
    const allItems = getAllSelectedItems();
    setBulkActionItems(allItems);
    setActionError(null);
    openModal(modal);
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
    onMove: canViewFolders
      ? () => {
          const allItems = getAllSelectedItems();
          setItemsToMove(allItems);
          openModal('move');
        }
      : undefined,
    onShare: () => {
      const allItems = getAllSelectedItems();
      const ids = allItems.map((i) => i.displayId);
      setShareEntityIds(ids);
      openModal('share');
    },
    onBulkAuthorise: () => openBulkModal('bulkAuthorise'),
    onBulkSetDefaultLayout: () => openBulkModal('bulkDefaultLayout'),
    onBulkCheckLicence: () => openBulkModal('bulkCheckLicence'),
    onBulkRequestScreenShot: () => openBulkModal('bulkRequestScreenShot'),
    onBulkCollectNow: () => openBulkModal('bulkCollectNow'),
    onBulkTriggerWebhook: () => openBulkModal('bulkTriggerWebhook'),
    onSetBandwidth: () => openBulkModal('setBandwidth'),
    onBulkSendCommand: () => openBulkModal('bulkSendCommand'),
    onBulkMoveCms: () => openBulkModal('bulkMoveCms'),
  });

  const { filterOptions } = useDisplaysFilterOptions(t);
  const libraryTabs = useFilteredTabs('displays');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      {canViewFolders && (
        <FolderSidebar
          isOpen={showFolderSidebar}
          selectedFolderId={selectedFolderId}
          onSelect={handleFolderChange}
          onClose={() => setShowFolderSidebar(false)}
          onAction={folderActions.openAction}
          refreshTrigger={folderRefreshTrigger}
        />
      )}

      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Displays" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              disabled={!isHydrated}
              onClick={() => openModal('add')}
              leftIcon={Plus}
            >
              {t('Add Display')}
            </Button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row justify-between items-center gap-4">
          <div className="w-full lg:flex-1 md:min-w-0">
            {canViewFolders && (
              <FolderBreadcrumb
                currentFolderId={selectedFolderId}
                onNavigate={handleFolderChange}
                isSidebarOpen={showFolderSidebar}
                onToggleSidebar={() => setShowFolderSidebar(!showFolderSidebar)}
                onAction={folderActions.openAction}
                refreshTrigger={folderRefreshTrigger}
              />
            )}
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
                placeholder={t('Search displays...')}
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
              } as DisplayFilterInput;
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

        <div className="min-h-0 flex flex-col flex-1">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading your displays...')}</span>
            </div>
          ) : viewMode === 'map' ? (
            <DataMap
              onRefresh={handleRefresh}
              viewMode="map"
              onViewModeChange={setViewMode}
              availableViewModes={['table', 'map']}
            >
              <DisplayMap
                filters={filterInputs}
                folderId={canViewFolders ? selectedFolderId : null}
              />
            </DataMap>
          ) : (
            <DataTable
              columns={columns}
              data={displayList}
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
              viewMode={viewMode}
              onViewModeChange={setViewMode}
              availableViewModes={['table', 'map']}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <DisplayModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
          isActionPending,
          actionError,
        }}
        selection={{
          selectedDisplay,
          itemsToDelete,
          itemsToMove,
          actionDisplay,
          bulkActionItems,
          shareEntityIds,
          setShareEntityIds,
        }}
        handlers={{
          confirmDelete,
          handleConfirmMove: (folderId) => handleConfirmMove(itemsToMove, folderId),
          confirmAuthorise,
          confirmCheckLicence,
          confirmRequestScreenShot,
          confirmCollectNow,
          confirmWakeOnLan,
          confirmPurgeAll,
          confirmTriggerWebhook,
          confirmSetDefaultLayout,
          confirmMoveCms,
          confirmMoveCmsCancel,
          confirmSetBandwidth,
          confirmBulkAuthorise,
          confirmBulkCheckLicence,
          confirmBulkRequestScreenShot,
          confirmBulkCollectNow,
          confirmBulkTriggerWebhook,
          confirmBulkSetDefaultLayout,
          confirmSendCommand,
          confirmBulkMoveCms,
        }}
      />
    </section>
  );
}
