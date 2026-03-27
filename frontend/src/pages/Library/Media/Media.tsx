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
import { Search, Filter, Folder, FilterX, Plus, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { MediaActionsProps, ModalType } from './MediaConfig';
import {
  filterMediaByPermission,
  getMediaItemActions,
  getMediaColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  type MediaFilterInput,
} from './MediaConfig';
import MediaCard from './components/MediaCard';
import { MediaModals } from './components/MediaModals';
import MediaPreviewer from './components/MediaPreviewer';
import { useMediaActions } from './hooks/useMediaActions';
import { useMediaData } from './hooks/useMediaData';
import { useMediaUpload } from './hooks/useMediaUpload';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import FolderSidebar from '@/components/ui/FolderSidebar';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import { DataGrid } from '@/components/ui/table/DataGrid';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useFolderActions } from '@/hooks/useFolderActions';
import { useOwner } from '@/hooks/useOwner';
import { usePermissions } from '@/hooks/usePermissions';
import { useTableState } from '@/hooks/useTableState';
import { useMediaFilterOptions } from '@/pages/Library/Media/hooks/useMediaFilterOptions';
import { downloadMedia, downloadMediaAsZip } from '@/services/mediaApi';
import type { Media } from '@/types/media';

export default function Media() {
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
  } = useTableState<MediaFilterInput>('media_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      mediaId: false,
      durationSeconds: false,
      fileSize: false,
      createdDt: false,
      modifiedDt: false,
      groupsWithPermissions: false,
      revised: false,
      released: false,
      fileName: false,
      expires: false,
      enableStat: false,
      ownerId: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
    folderId: canViewFolders ? homeFolderId : null,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, Media>>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [previewItem, setPreviewItem] = useState<Media | null>(null);
  const [showInfoPanel, setShowInfoPanel] = useState(false);

  const [selectedFolderName, setSelectedFolderName] = useState(t('Root Folder'));
  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const [itemsToDelete, setItemsToDelete] = useState<Media[]>([]);
  const [itemsToMove, setItemsToMove] = useState<Media[]>([]);
  const [shareEntityIds, setShareEntityIds] = useState<number | number[] | null>(null);
  const [selectedMediaId, setSelectedMediaId] = useState<number | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const targetUploadFolderId = canViewFolders ? (selectedFolderId ?? homeFolderId) : homeFolderId;

  const canAddToFolder = targetUploadFolderId !== null;

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['media'] });
  };

  const {
    isAddModalOpen,
    setAddModalOpen,
    queue,
    removeFile,
    clearQueue,
    updateFileData,
    addUrlToQueue,
    dropzone,
    handleManualAddFiles,
    handleStartUpload,
    handleCancelUpload,
  } = useMediaUpload({
    targetUploadFolderId,
    canAddToFolder,
    handleRefresh,
  });

  const {
    getRootProps: getGlobalRootProps,
    getInputProps: getGlobalInputProps,
    isDragActive: isGlobalDragActive,
  } = dropzone;

  // Data fetching
  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useMediaData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    folderId: canViewFolders ? selectedFolderId : null,
    enabled: isHydrated,
  });

  // Computed values
  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [mediaList, setMediaList] = useState<Media[]>([]);

  const folderActions = useFolderActions({
    onSuccess: (targetFolder) => {
      setFolderRefreshTrigger((prev) => prev + 1);

      // Select home folder
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

  useEffect(() => {
    setMediaList(data ?? []);
  }, [data]);

  // Update the selected cache when data loads or selection changes
  useEffect(() => {
    if (!mediaList || mediaList.length === 0) {
      return;
    }

    setSelectionCache((prev) => {
      const next = { ...prev };
      let hasChanges = false;

      mediaList.forEach((item) => {
        const id = item.mediaId.toString();
        if (rowSelection[id] && next[id] !== item) {
          next[id] = item;
          hasChanges = true;
        }
      });

      return hasChanges ? next : prev;
    });
  }, [mediaList, rowSelection]);

  const selectedMedia = mediaList.find((m) => m.mediaId === selectedMediaId) ?? null;
  const existingNames = mediaList.map((m) => m.name);
  const ownerId = selectedMedia?.ownerId ? Number(selectedMedia.ownerId) : null;
  const { owner, loading } = useOwner({ ownerId });

  const getRowId = (row: Media) => row.mediaId.toString();

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
    confirmDelete,
    handleConfirmClone,
    handleConfirmMove,
  } = useMediaActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
    setItemsToMove,
  });

  const handleDelete = (id: number) => {
    const media = mediaList.find((m) => m.mediaId === id);
    if (!media) {
      return;
    }

    setDeleteError(null);
    setItemsToDelete([media]);
    openModal('delete');
  };

  const handleDownload = async (row: Media) => {
    try {
      await downloadMedia(row.mediaId, row.storedAs);
      notify.success(t('Download started!'));
    } catch (error) {
      console.error('Download failed', error);
    }
  };

  const handlePreviewClick = (row: Media) => {
    setPreviewItem(row);
  };

  const openEditModal = (media: Media) => {
    setSelectedMediaId(media.mediaId);
    openModal('edit');
  };

  const openReplaceFileModal = (mediaId: number) => {
    setSelectedMediaId(mediaId);
    openModal('replace');
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const openCopyModal = (mediaId: number) => {
    setSelectedMediaId(mediaId);
    openModal('copy');
  };

  const columns = getMediaColumns({
    t,
    onPreview: handlePreviewClick,
    onDelete: handleDelete,
    onDownload: handleDownload,
    openEditModal,
    openMoveModal: canViewFolders
      ? (media) => {
          setItemsToMove([media] as Media[]);
          openModal('move');
        }
      : undefined,
    openShareModal: (mediaId) => {
      setShareEntityIds(mediaId);
      openModal('share');
    },
    openDetails: (mediaId) => {
      setSelectedMediaId(mediaId);
      setShowInfoPanel(true);
    },
    copyMedia: openCopyModal,
    openReplaceModal: openReplaceFileModal,
  });

  const getAllSelectedItems = (): Media[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is Media => !!item);
  };

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const permittedItems = filterMediaByPermission(
        getAllSelectedItems(),
        (item: Media) => item.userPermissions.delete,
        t,
        t('delete'),
      );

      if (permittedItems.length === 0) {
        return;
      }

      setDeleteError(null);
      setItemsToDelete(permittedItems);
      openModal('delete');
    },
    onMove: canViewFolders
      ? () => {
          const allItems = getAllSelectedItems();
          const permittedItems = allItems.filter((item) => item.userPermissions.edit);

          if (permittedItems.length === 0) {
            notify.warning(t('You do not have permission to move any of the selected items.'));
            return;
          }

          if (permittedItems.length < allItems.length) {
            notify.info(
              t('{{count}} items were skipped due to lack of permissions.', {
                count: allItems.length - permittedItems.length,
              }),
            );
          }

          setItemsToMove(permittedItems);
          openModal('move');
        }
      : undefined,
    onShare: () => {
      const allItems = getAllSelectedItems();
      const permittedItems = allItems.filter((item) => item.userPermissions.modifyPermissions);

      if (permittedItems.length === 0) {
        notify.warning(t('You do not have permission to share any of the selected items.'));
        return;
      }

      if (permittedItems.length < allItems.length) {
        notify.info(
          t('{{count}} items were skipped due to lack of permissions.', {
            count: allItems.length - permittedItems.length,
          }),
        );
      }

      const ids = permittedItems.map((i) => i.mediaId);

      setShareEntityIds(ids);
      openModal('share');
    },
    onDownload: async () => {
      const allItems = getAllSelectedItems();

      if (allItems.length === 0) {
        return;
      }

      try {
        if (allItems.length === 1) {
          // Normal single-file download
          const item = allItems[0];

          if (item) {
            await downloadMedia(item.mediaId, item.fileName);
            notify.success(t('Download started!'));
          }
        } else {
          // Multiple items ZIP download
          notify.info(
            t('Zipping {{count}} files. You can continue using the app.', {
              count: allItems.length,
            }),
          );

          const itemsToZip = allItems.map((item) => {
            return {
              mediaId: item.mediaId,
              fileName: item.fileName || item.name,
            };
          });

          setRowSelection({});

          await downloadMediaAsZip(itemsToZip, 'media_page_export.zip');

          notify.success(t('ZIP file generated and download started!'));
        }
      } catch (error) {
        console.error('Failed to package media files:', error);
        notify.error(t('An error occurred while zipping the files.'));
      }
    },
  });

  const getMediaActions = getMediaItemActions({
    t,
    onDelete: handleDelete,
    onDownload: handleDownload,
    openEditModal,
    onPreview: handlePreviewClick,
    openMoveModal: canViewFolders
      ? (media) => {
          setItemsToMove([media] as Media[]);
          openModal('move');
        }
      : undefined,
    openShareModal: (mediaId) => {
      setShareEntityIds(mediaId);
      openModal('share');
    },
    openDetails: (mediaId) => {
      setSelectedMediaId(mediaId);
      setShowInfoPanel(true);
    },
    copyMedia: openCopyModal,
    openReplaceModal: openReplaceFileModal,
  } as MediaActionsProps);

  const { filterOptions } = useMediaFilterOptions(t);

  const libraryTabs = useFilteredTabs('library');

  return (
    <section
      {...getGlobalRootProps()}
      className="flex h-full w-full min-h-0 relative outline-none overflow-hidden"
    >
      <input {...getGlobalInputProps()} />

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
        {isGlobalDragActive && !isAddModalOpen && (
          <div className="absolute bottom-14 left-1/2 -translate-x-1/2 z-50 justify-center flex flex-col items-center gap-3 mb-0">
            {canAddToFolder ? (
              <>
                <span className="inline-flex justify-center items-center size-15.5 shadow-lg rounded-full border-7 animate-bounce border-blue-50 bg-xibo-blue-100 text-blue-800">
                  <Upload className="shrink-0 size-6.5" />
                </span>
                <div className="bg-slate-50 border border-gray-200 px-4 py-2 shadow-lg rounded-full flex justify-center items-center gap-2">
                  <span className="text-sm text-gray-800">{t('Upload files to ')}</span>
                  <span className="text-xibo-blue-600 font-semibold flex">
                    <div className="size-6.5 flex justify-center items-center">
                      <Folder className="size-4" />
                    </div>
                    {canViewFolders ? `"${selectedFolderName}"` : t('Home Folder')}
                  </span>
                </div>
              </>
            ) : (
              <div className="bg-slate-50 border border-gray-200 px-4 py-2 shadow-lg rounded-full flex justify-center items-center">
                <span className="text-sm font-bold text-red-800">
                  {t('You cannot upload files here')}
                </span>
              </div>
            )}
          </div>
        )}

        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Media" navigation={libraryTabs} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button variant="primary" onClick={() => setAddModalOpen(true)} leftIcon={Plus}>
              {t('Add Media')}
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
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search media...')}
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
          ) : viewMode === 'table' ? (
            <DataTable
              columns={columns}
              data={mediaList}
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
              viewMode="table"
              onViewModeChange={setViewMode}
              getRowId={getRowId}
            />
          ) : (
            <DataGrid
              data={mediaList}
              pageCount={pageCount}
              pagination={pagination}
              onPaginationChange={setPagination}
              rowSelection={rowSelection}
              onRowSelectionChange={setRowSelection}
              loading={isFetching}
              onRefresh={handleRefresh}
              bulkActions={bulkActions}
              viewMode="grid"
              onViewModeChange={setViewMode}
              getRowId={getRowId}
              renderCard={(media, isSelected, toggleSelect) => (
                <MediaCard
                  key={media.mediaId}
                  media={media}
                  isSelected={isSelected}
                  onToggleSelect={toggleSelect}
                  onPreview={handlePreviewClick}
                  actions={getMediaActions(media)}
                />
              )}
            />
          )}
        </div>
      </div>

      <MediaPreviewer
        mediaId={previewItem?.mediaId ?? null}
        mediaType={previewItem?.mediaType}
        fileName={previewItem?.name}
        mediaData={previewItem}
        onDownload={() => previewItem && handleDownload(previewItem)}
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
        folderName={selectedFolderName}
      />

      <MediaModals
        actions={{
          activeModal,
          closeModal,
          handleRefresh,
          setMediaList,
          deleteError,
          isDeleting,
          isCloning,
        }}
        selection={{
          selectedMedia,
          itemsToDelete,
          itemsToMove,
          existingNames,
          shareEntityIds,
          setShareEntityIds,
        }}
        handlers={{
          confirmDelete: (opts) => confirmDelete(itemsToDelete, opts),
          handleConfirmClone: (name, tags) => handleConfirmClone(selectedMedia, name, tags),
          handleConfirmMove: (folderId) => handleConfirmMove(itemsToMove, folderId),
        }}
        upload={{
          isOpen: isAddModalOpen,
          setOpen: setAddModalOpen,
          queue,
          onStart: handleStartUpload,
          onCancel: handleCancelUpload,
          onManualAdd: handleManualAddFiles,
          onUrlAdd: addUrlToQueue,
          removeFile,
          updateFileData,
          clearQueue,
          canAdd: canAddToFolder,
          targetFolderId: targetUploadFolderId,
          selectedFolderId,
          setSelectedFolderId,
          canViewFolders,
        }}
        infoPanel={{
          isOpen: showInfoPanel,
          setOpen: setShowInfoPanel,
          setSelectedMediaId,
          owner,
          loading,
          folderName: selectedFolderName,
        }}
        folderActions={folderActions}
      />
    </section>
  );
}
