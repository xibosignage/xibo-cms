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
import { Filter, FilterX, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import type { ModalType } from './MenuBoardConfig';
import {
  getBulkActions,
  getMenuBoardColumns,
  INITIAL_FILTER_STATE,
  type MenuBoardFilterInput,
} from './MenuBoardConfig';
import { MenuBoardModals } from './components/MenuBoardModals';
import { useMenuBoardActions } from './hooks/useMenuBoardActions';
import { useMenuBoardData } from './hooks/useMenuBoardData';
import { useMenuBoardFilterOptions } from './hooks/useMenuBoardFilterOptions';

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
import { fetchContextButtons } from '@/services/folderApi';
import type { MenuBoard } from '@/types/menuBoard';

export default function MenuBoards() {
  const { t } = useTranslation();
  const navigate = useNavigate();
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
    globalFilter,
    debouncedFilter,
    setGlobalFilter,
    filterInputs,
    setFilterInputs,
    folderId: selectedFolderId,
    setFolderId: setSelectedFolderId,
    isHydrated,
  } = useTableState<MenuBoardFilterInput>('menuBoard_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      menuId: true,
      name: true,
      description: true,
      code: true,
      owner: true,
      groupsWithPermissions: true,
      modifiedDt: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
    folderId: canViewFolders ? homeFolderId : null,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, MenuBoard>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType>(null);

  const [itemsToDelete, setItemsToDelete] = useState<MenuBoard[]>([]);
  const [itemsToMove, setItemsToMove] = useState<MenuBoard[]>([]);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);
  const [selectedMenuBoardId, setSelectedMenuBoardId] = useState<number | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useMenuBoardData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    folderId: selectedFolderId,
    enabled: isHydrated,
  });

  const effectiveFolderId = selectedFolderId ?? homeFolderId;
  const { data: folderPerms } = useQuery({
    queryKey: ['folderPermissions', effectiveFolderId],
    queryFn: () => fetchContextButtons(effectiveFolderId),
    staleTime: 1000 * 60 * 5,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const menuBoardList = data ?? [];
  const canAddToFolder = folderPerms?.create || false;

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

  const getRowId = (row: MenuBoard) => row.menuId.toString();

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      menuBoardList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const selectedMenuBoard = menuBoardList.find((m) => m.menuId === selectedMenuBoardId) ?? null;
  const existingNames = menuBoardList.map((m) => m.name);

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['menuBoard'] });
  };

  const handleFolderChange = (folder: { id: number | null; text: string | '' }) => {
    setSelectedFolderId(folder.id);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setRowSelection({});
  };

  const {
    isDeleting,
    deleteError,
    setDeleteError,
    isCloning,
    confirmDelete,
    handleConfirmClone,
    handleConfirmMove,
  } = useMenuBoardActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
    setItemsToMove,
  });

  const handleDelete = (id: number) => {
    const menuBoard = menuBoardList.find((m) => m.menuId === id);
    if (!menuBoard) {
      return;
    }
    setItemsToDelete([menuBoard]);
    setDeleteError(null);
    openModal('delete');
  };

  const openAddEditModal = (menuBoard: MenuBoard | null) => {
    setSelectedMenuBoardId(menuBoard ? menuBoard.menuId : null);
    openModal('edit');
  };

  const openCopyModal = (menuBoardId: number) => {
    setSelectedMenuBoardId(menuBoardId);
    openModal('copy');
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getMenuBoardColumns({
    t,
    onDelete: handleDelete,
    openAddEditModal,
    openMoveModal: (menuBoard) => {
      setItemsToMove([menuBoard]);
      openModal('move');
    },
    openShareModal: (menuBoardId) => {
      setShareEntityIds(menuBoardId);
      openModal('share');
    },
    copyMenuBoard: openCopyModal,
    onNavigate: (path) => {
      navigate(path);
    },
  });

  const getAllSelectedItems = (): MenuBoard[] =>
    Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is MenuBoard => !!item);

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
      const ids = allItems.map((i) => i.menuId);
      setShareEntityIds(ids);
      openModal('share');
    },
  });

  const { filterOptions } = useMenuBoardFilterOptions(t);
  const libraryTabs = useFilteredTabs('library');

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
          <TabNav activeTab="Menu Boards" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              disabled={!canAddToFolder || !isHydrated}
              onClick={() => openAddEditModal(null)}
              leftIcon={Plus}
            >
              {t('Add Menu Board')}
            </Button>
          </div>
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
                placeholder={t('Search menu boards...')}
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
              <span className="text-gray-400 font-medium">{t('Loading your preferences...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={menuBoardList}
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

      <MenuBoardModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
          isCloning,
        }}
        selection={{
          selectedMenuBoard,
          selectedMenuBoardId,
          defaultFolderId: effectiveFolderId,
          itemsToDelete,
          itemsToMove,
          existingNames,
          shareEntityIds,
          setShareEntityIds,
        }}
        handlers={{
          confirmDelete: () => confirmDelete(itemsToDelete),
          handleConfirmClone: (name, description, code) =>
            handleConfirmClone(selectedMenuBoard, name, description, code),
          handleConfirmMove: (folderId) => handleConfirmMove(itemsToMove, folderId),
        }}
        folderActions={folderActions}
      />
    </section>
  );
}
