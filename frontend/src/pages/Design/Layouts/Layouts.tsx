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

import type { LayoutFilterInput } from './LayoutConfig';
import { getBulkActions, getLayoutColumns, LAYOUT_INITIAL_FILTER_STATE } from './LayoutConfig';
import LayoutPreviewer from './components/LayoutPreviewer';
import { LayoutModals } from './components/LayoutsModal';
import { useLayoutActions } from './hooks/useLayoutActions';
import { useLayoutData } from './hooks/useLayoutData';
import { useLayoutFilterOptions } from './hooks/useLayoutFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import FolderSidebar from '@/components/ui/FolderSidebar';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useFolderActions } from '@/hooks/useFolderActions';
import { useOwner } from '@/hooks/useOwner';
import { usePermissions } from '@/hooks/usePermissions';
import { useTableState } from '@/hooks/useTableState';
import type { ModalType } from '@/pages/Library/Media/MediaConfig';
import { fetchContextButtons } from '@/services/folderApi';
import type { Layout } from '@/types/layout';

export default function Layouts() {
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
  } = useTableState<LayoutFilterInput>('layout_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      campaignId: true,
      layout: true,
      publishedStatus: true,
      duration: true,
      description: true,
      thumbnail: true,
      owner: true,
      groupsWithPermissions: false,
      valid: true,
      status: true,
      modifiedDt: false,
      layoutId: false,
      code: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: LAYOUT_INITIAL_FILTER_STATE,
    folderId: canViewFolders ? homeFolderId : null,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Layout>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [showInfoPanel, setShowInfoPanel] = useState(false);

  const [selectedFolderName, setSelectedFolderName] = useState(t('Root Folder'));
  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const [itemsToDelete, setItemsToDelete] = useState<Layout[]>([]);
  const [itemsToMove, setItemsToMove] = useState<Layout[]>([]);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);
  const [selectedLayoutId, setSelectedLayoutId] = useState<number | null>(null);
  const [previewItem, setPreviewItem] = useState<Layout | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useLayoutData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    folderId: selectedFolderId,
    enabled: isHydrated,
  });

  const { data: folderPerms } = useQuery({
    queryKey: ['folderPermissions', selectedFolderId],
    queryFn: () => fetchContextButtons(selectedFolderId as number),
    enabled: selectedFolderId !== null,
    staleTime: 1000 * 60 * 5,
  });

  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const layoutList = data ?? [];
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

  const getRowId = (row: Layout) => {
    return row.layoutId.toString();
  };

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      layoutList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const selectedLayout = layoutList.find((m) => m.layoutId === selectedLayoutId) ?? null;
  const existingNames = layoutList.map((m) => m.name || m.layout).filter(Boolean);
  const ownerId = selectedLayout?.ownerId ? Number(selectedLayout.ownerId) : null;
  const { owner, loading } = useOwner({ ownerId });

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['layout'] });
  };

  const handleFolderChange = (folder: { id: number | null; text: string | '' }) => {
    setSelectedFolderId(folder.id);
    setSelectedFolderName(folder.text);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setRowSelection({});
  };

  const {
    isDeleting,
    deleteError,
    setDeleteError,
    isCloning,
    isPublishing,
    isAssigning,
    confirmDelete,
    handleConfirmClone,
    handleConfirmMove,
    handleCreateLayout,
    handleOpenLayout,
    confirmPublish,
    handleCheckoutLayout,
    isDiscarding,
    handleConfirmDiscard,
    handleConfirmAssign,
    handleJumpToPlaylists,
    handleJumpToCampaigns,
    handleJumpToMedia,
    isExporting,
    handleExportLayout,
  } = useLayoutActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
    setItemsToMove,
  });

  const handleResetFilters = () => {
    setFilterInputs(LAYOUT_INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const openEditModal = (layout: Layout) => {
    setSelectedLayoutId(layout.layoutId);
    openModal('edit');
  };

  const openShareModal = (campaignId: number) => {
    setShareEntityIds(campaignId);
    openModal('share');
  };

  const handleDelete = (id: number) => {
    const playlist = layoutList.find((m) => m.layoutId === id);
    if (!playlist) return;

    setItemsToDelete([playlist]);
    setDeleteError(null);
    openModal('delete');
  };

  const handlePreviewClick = (row: Layout) => {
    setPreviewItem(row);
  };

  const openCopyModal = (layoutId: number) => {
    setSelectedLayoutId(layoutId);
    openModal('copy');
  };

  const openPublish = (layoutId: number) => {
    setSelectedLayoutId(layoutId);
    openModal('publish');
  };

  const handleDiscardModal = (layoutId: number) => {
    setSelectedLayoutId(layoutId);
    openModal('discard');
  };

  const handleExportModal = (layoutId: number) => {
    setSelectedLayoutId(layoutId);
    openModal('export');
  };

  const openTemplateModal = (layoutId: number) => {
    setSelectedLayoutId(layoutId);
    openModal('template');
  };

  const openRetireModal = (row: Layout) => {
    setSelectedLayoutId(row.layoutId);
    openModal('retire');
  };

  const openEnableStatsModal = (row: Layout) => {
    setSelectedLayoutId(row.layoutId);
    openModal('enableStats');
  };

  const columns = getLayoutColumns({
    t,
    onDelete: handleDelete,
    openEditModal,
    openMoveModal: canViewFolders
      ? (layout) => {
          setItemsToMove([layout] as Layout[]);
          openModal('move');
        }
      : undefined,
    openShareModal,
    copyLayout: openCopyModal,
    openDetails: (layoutId) => {
      setSelectedLayoutId(layoutId);
      setShowInfoPanel(true);
    },
    onPreview: handlePreviewClick,
    openLayout: (layoutId) => {
      handleOpenLayout(layoutId);
    },
    openPublish,
    checkoutLayout: (layoutId) => {
      handleCheckoutLayout(layoutId);
    },
    discardLayout: handleDiscardModal,
    assignModal: (layout) => {
      setSelectedLayoutId(layout.layoutId);
      openModal('campaign');
    },
    jumpToPlaylists: handleJumpToPlaylists,
    jumpToCampaigns: handleJumpToCampaigns,
    jumpToMedia: handleJumpToMedia,
    exportLayout: (layout) => {
      handleExportModal(layout.layoutId);
    },
    openTemplateModal,
    openRetireModal,
    openEnableStatsModal,
  });

  const getAllSelectedItems = (): Layout[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Layout => !!item);
  };

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
    onShare: () => {
      const allItems = getAllSelectedItems();
      const ids = allItems.map((i) => i.layoutId);
      setShareEntityIds(ids);
      openModal('share');
    },
  });

  const { filterOptions } = useLayoutFilterOptions(t);

  const libraryTabs = useFilteredTabs('design');

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
          <TabNav activeTab="Layouts" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button
              variant="primary"
              className="font-semibold"
              onClick={handleCreateLayout}
              disabled={!canAddToFolder || !isHydrated}
              leftIcon={Plus}
            >
              {t('New Layout')}
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
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search layouts...')}
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
              <span className="text-gray-400 font-medium">
                {t('Loading your layout preferences...')}
              </span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={layoutList}
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
              columnPinning={{
                left: ['tableSelection'],
                right: ['tableActions'],
              }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={bulkActions}
              viewMode={'table'}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>
      <LayoutModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          deleteError,
          isDeleting,
          isCloning,
          isPublishing,
          isDiscarding,
          isAssigning,
          isExporting,
        }}
        selection={{
          selectedLayout,
          itemsToMove,
          shareEntityIds,
          setShareEntityIds,
          itemsToDelete,
          existingNames,
        }}
        handlers={{
          confirmDelete,
          handleConfirmClone: (name, description, copyMedia) =>
            handleConfirmClone(selectedLayout, name, description, copyMedia),
          handleConfirmMove: (folderId) => handleConfirmMove(itemsToMove, folderId),
          confirmPublish,
          confirmDiscard: handleConfirmDiscard,
          handleConfirmAssign,
          handleExportLayout,
        }}
        infoPanel={{
          isOpen: showInfoPanel,
          setOpen: setShowInfoPanel,
          owner,
          loading,
          folderName: selectedFolderName,
          setSelectedLayoutId,
        }}
        folderActions={folderActions}
      />
      <LayoutPreviewer
        layoutId={previewItem && previewItem?.layoutId}
        name={selectedLayout?.name}
        onClose={() => {
          setPreviewItem(null);
        }}
        onShare={
          previewItem?.userPermissions?.modifyPermissions
            ? (mediaId) => {
                setShareEntityIds(mediaId);
                openModal('share');
              }
            : undefined
        }
        onMove={
          canViewFolders
            ? () => {
                if (!previewItem) {
                  return;
                }

                setItemsToMove([previewItem]);
                openModal('move');
              }
            : undefined
        }
        layoutData={previewItem}
        folderName={selectedFolderName}
      />
    </section>
  );
}
