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
import {
  type SortingState,
  type PaginationState,
  type RowSelectionState,
} from '@tanstack/react-table';
import { Search, Filter, Folder, FilterX, Plus, Upload } from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { useDropzone } from 'react-dropzone';
import { useTranslation } from 'react-i18next';

import ShareModal from '../../../components/ui/modals/ShareModal';

import { getMediaItemActions } from './MediaConfig';
import {
  getMediaColumns,
  getBulkActions,
  INITIAL_FILTER_STATE,
  LIBRARY_TABS,
  type MediaFilterInput,
  ACCEPTED_MIME_TYPES,
} from './MediaConfig';
import DeleteMediaModal from './components/DeleteMediaModal';
import MediaCard from './components/MediaCard';
import MediaPreviewer from './components/MediaPreviewer';
import { UploadProgressDock } from './components/UploadProgressDock';
import { useMediaData } from './hooks/useMediaData';

import Button from '@/components/ui/Button';
import { FileUploader } from '@/components/ui/FileUploader';
import FilterInputs from '@/components/ui/FilterInputs';
import FolderActionModals from '@/components/ui/FolderActionModals';
import FolderBreadcrumb from '@/components/ui/FolderBreadCrumb';
import FolderSidebar from '@/components/ui/FolderSidebar';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import Modal from '@/components/ui/modals/Modal';
import { DataGrid } from '@/components/ui/table/DataGrid';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUploadContext } from '@/context/UploadContext';
import { useDebounce } from '@/hooks/useDebounce';
import { useFolderActions } from '@/hooks/useFolderActions';
import EditMediaModal from '@/pages/Library/Media/components/EditMediaModal';
import { useMediaFilterOptions } from '@/pages/Library/Media/hooks/useMediaFilterOptions';
import { fetchContextButtons } from '@/services/folderApi';
import { deleteMedia, downloadMedia } from '@/services/mediaApi';
import type { Media } from '@/types/media';

export default function Media() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const [folderRefreshTrigger, setFolderRefreshTrigger] = useState(0);
  const [sorting, setSorting] = useState<SortingState>([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [previewItem, setPreviewItem] = useState<Media | null>(null);
  const [filterInputs, setFilterInput] = useState<MediaFilterInput>(INITIAL_FILTER_STATE);
  const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');

  const [isAddModalOpen, setAddModalOpen] = useState(false);
  const [selectedFolderId, setSelectedFolderId] = useState<number | null>(1);
  const [selectedFolderName, setSelectedFolderName] = useState(t('Root Folder'));
  const [canAddToFolder, setCanAddToFolder] = useState(false);

  const [showFolderSidebar, setShowFolderSidebar] = useState(false);
  const [openModal, setOpenModal] = useState({
    edit: false,
    share: false,
    delete: false,
  });

  const [itemsToDelete, setItemsToDelete] = useState<Media[]>([]);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const [selectedMediaId, setSelectedMediaId] = useState<number | null>(null);

  const debouncedFilter = useDebounce(globalFilter, 500);

  const { queue, addFiles, removeFile, clearQueue, updateFileData, saveMetadata, addUrlToQueue } =
    useUploadContext();

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
    folderId: selectedFolderId,
  });

  // Computed values
  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [mediaList, setMediaList] = useState<Media[]>([]);

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

  useEffect(() => {
    setMediaList(data ?? []);
  }, [data]);

  // Check selected folder permission to add media
  useEffect(() => {
    if (selectedFolderId === null) {
      setCanAddToFolder(false);
      return;
    }

    let active = true;

    fetchContextButtons(selectedFolderId)
      .then((perms) => {
        if (active) {
          setCanAddToFolder(perms.create || false);
        }
      })
      .catch((err) => {
        console.error('Failed to fetch folder permissions', err);
        if (active) {
          setCanAddToFolder(false);
        }
      });

    return () => {
      active = false;
    };
  }, [selectedFolderId]);

  const uploadStateRef = useRef({ selectedFolderId, canCreate: canAddToFolder });
  useEffect(() => {
    uploadStateRef.current = { selectedFolderId, canCreate: canAddToFolder };
  }, [selectedFolderId, canAddToFolder]);

  const selectedMedia = mediaList.find((m) => m.mediaId === selectedMediaId) ?? null;

  const getRowId = (row: Media) => row.mediaId.toString();

  // Event handlers
  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['media'] });
  };

  const handleFolderChange = (folder: { id: number | null; text: string | '' }) => {
    setSelectedFolderId(folder.id);
    setSelectedFolderName(folder.text);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setRowSelection({});
  };

  // Handles dropping files anywhere on the screen
  const onGlobalDrop = (acceptedFiles: File[]) => {
    const { selectedFolderId: currentId, canCreate: hasPermission } = uploadStateRef.current;

    if (acceptedFiles.length > 0 && currentId != null && hasPermission) {
      setAddModalOpen(true);
      addFiles(acceptedFiles, currentId);
      notify.success(t('Files added to queue'));
    } else if (currentId != null && !hasPermission) {
      notify.error(t('You do not have permission to upload to this folder'));
    }
  };

  const {
    getRootProps: getGlobalRootProps,
    getInputProps: getGlobalInputProps,
    isDragActive: isGlobalDragActive,
  } = useDropzone({
    onDrop: onGlobalDrop,
    noClick: true,
    noKeyboard: true,
    accept: ACCEPTED_MIME_TYPES,
  });

  const handleManualAddFiles = (files: File[]) => {
    if (!selectedFolderId || !canAddToFolder) {
      return;
    }

    addFiles(files, selectedFolderId);
  };

  const handleDelete = (id: number) => {
    const media = mediaList.find((m) => m.mediaId === id);
    if (!media) return;

    setItemsToDelete([media]);
    setDeleteError(null);
    toggleModal('delete', true);
  };

  const confirmDelete = async () => {
    if (itemsToDelete.length === 0 || isDeleting) return;

    try {
      setIsDeleting(true);

      const results = await Promise.allSettled(
        itemsToDelete.map((item) => deleteMedia(item.mediaId)),
      );

      const failed = results.filter((r) => r.status === 'rejected');

      if (failed.length > 0) {
        setDeleteError(`${failed.length} item(s) could not be deleted because they are in use.`);
      }

      setRowSelection({});
      handleRefresh();
      toggleModal('delete', false);
    } catch (error) {
      console.error(error);
      setDeleteError('Some selected items are in use and cannot be deleted.');
    } finally {
      setIsDeleting(false);
    }
  };

  const handleBulkDelete = (selectedItems: Media[]) => {
    if (selectedItems.length === 0) return;

    setItemsToDelete(selectedItems);
    setDeleteError(null);
    toggleModal('delete', true);
  };

  const handleDownload = async (row: Media) => {
    try {
      await downloadMedia(row.mediaId, row.storedAs);
    } catch (error) {
      console.error('Download failed', error);
    }
  };

  const handlePreviewClick = (row: Media) => {
    setPreviewItem(row);
  };

  const handleStartUpload = async () => {
    await saveMetadata();

    const hasPending = queue.some(
      (item) => item.status === 'uploading' || item.status === 'pending',
    );

    if (!hasPending) {
      clearQueue();
    }

    setAddModalOpen(false);
    handleRefresh();
  };

  const handleCancelUpload = () => {
    clearQueue();
    setAddModalOpen(false);
    handleRefresh();
  };

  const openEditModal = (media: Media) => {
    setSelectedMediaId(media.mediaId);
    toggleModal('edit', true);
  };

  const closeEditModal = () => {
    toggleModal('edit', false);
    setSelectedMediaId(null);
  };

  const toggleModal = (name: string, isOpen: boolean) => {
    setOpenModal((prev) => ({ ...prev, [name]: isOpen }));
  };

  const handleResetFilters = () => {
    setFilterInput(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getMediaColumns({
    t,
    onPreview: handlePreviewClick,
    onDelete: handleDelete,
    onDownload: handleDownload,
    openEditModal,
    openShareModal: () => toggleModal('share', true),
  });

  const bulkActions = getBulkActions({
    t,
    onDelete: handleBulkDelete,
    onMove: (items) => console.log('Move', items),
    onShare: (items) => console.log('Share', items),
  });

  const addModalActions = [
    {
      label: t('Cancel'),
      onClick: handleCancelUpload,
      variant: 'secondary' as const,
      className: 'bg-transparent',
    },
    {
      label: t('Done'),
      onClick: handleStartUpload,
      variant: 'primary' as const,
      disabled: queue.length === 0,
    },
  ];

  const getMediaActions = getMediaItemActions({
    t,
    onDelete: handleDelete,
    onDownload: handleDownload,
    openEditModal,
    onPreview: handlePreviewClick,
  });

  const { filterOptions } = useMediaFilterOptions();

  return (
    <section
      {...getGlobalRootProps()}
      className="flex h-full w-full min-h-0 relative outline-none overflow-hidden"
    >
      <input {...getGlobalInputProps()} />

      <FolderSidebar
        isOpen={showFolderSidebar}
        selectedFolderId={selectedFolderId}
        onSelect={handleFolderChange}
        onClose={() => setShowFolderSidebar(false)}
        onAction={folderActions.openAction}
        refreshTrigger={folderRefreshTrigger}
      />

      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        {isGlobalDragActive && !isAddModalOpen && (
          <div className="absolute bottom-14 left-1/2 -translate-x-1/2 z-50 justify-center flex flex-col items-center gap-3 mb-0">
            {selectedFolderId != null ? (
              canAddToFolder ? (
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
                      {`"${selectedFolderName}"`}
                    </span>
                  </div>
                </>
              ) : (
                <div className="bg-slate-50 border border-gray-200 px-4 py-2 shadow-lg rounded-full flex justify-center items-center">
                  <span className="text-sm font-bold text-red-800">
                    {t('You cannot upload files to')}
                    <span className="ml-1">"{selectedFolderName}"</span>
                  </span>
                </div>
              )
            ) : (
              <div className="bg-slate-50 border border-gray-200 px-4 py-2 shadow-lg rounded-full flex justify-center items-center">
                <span className="text-sm font-bold text-red-800">
                  {t('Remove filter "All Items" to upload to a folder!')}
                </span>
              </div>
            )}
          </div>
        )}

        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Media" navigation={LIBRARY_TABS} />
          <div className="flex items-center gap-2 md:mb-0">
            <Button variant="primary" onClick={() => setAddModalOpen(true)} leftIcon={Plus}>
              {t('Add Media')}
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

          <div className="flex items-center gap-2 w-full xl:w-[460px] lg:w-[300px] shrink-0">
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
                className="py-2 px-3 pl-10 block h-[45px] bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
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
            setFilterInput((prev) => ({ ...prev, [name]: value }));
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
          open={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
            {error}
          </div>
        )}

        <div className="flex-1 min-h-0 flex flex-col">
          {viewMode === 'table' ? (
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
              columnPinning={{
                left: ['tableSelection'],
                right: ['tableActions'],
              }}
              initialState={{
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
              }}
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

      <Modal
        isOpen={isAddModalOpen}
        onClose={handleCancelUpload}
        title={t('Add Media')}
        actions={addModalActions}
        size="lg"
      >
        <div className="flex flex-col gap-3 p-8 pt-0">
          <SelectFolder
            selectedId={selectedFolderId}
            onSelect={(folder) => {
              setSelectedFolderId(folder.id);
            }}
          />

          <FileUploader
            queue={queue}
            acceptedFileTypes={ACCEPTED_MIME_TYPES}
            addFiles={handleManualAddFiles}
            removeFile={removeFile}
            clearQueue={clearQueue}
            updateFileData={updateFileData}
            isUploading={false}
            maxSize={2 * 1024 * 1024 * 1024}
            disabled={!canAddToFolder || selectedFolderId === null}
            onUrlUpload={(url) => {
              if (!selectedFolderId) {
                return;
              }

              addUrlToQueue(url, selectedFolderId);
            }}
          />
        </div>
      </Modal>

      <MediaPreviewer
        mediaId={previewItem?.mediaId ?? null}
        mediaType={previewItem?.mediaType}
        fileName={previewItem?.name}
        mediaData={previewItem}
        onDownload={() => previewItem && handleDownload(previewItem)}
        onClose={() => {
          setPreviewItem(null);
          handleRefresh();
        }}
      />

      {selectedMedia && (
        <EditMediaModal
          openModal={openModal.edit}
          onClose={() => {
            toggleModal('edit', false);
            closeEditModal();
          }}
          onSave={(updatedMedia) => {
            setMediaList((prev) =>
              prev.map((m) => (m.mediaId === updatedMedia.mediaId ? updatedMedia : m)),
            );
            handleRefresh();
          }}
          data={selectedMedia}
        />
      )}
      <ShareModal onClose={() => toggleModal('share', false)} openModal={openModal.share} />

      <UploadProgressDock isModalOpen={isAddModalOpen} />

      <FolderActionModals folderActions={folderActions} />

      <DeleteMediaModal
        isOpen={openModal.delete}
        onClose={() => toggleModal('delete', false)}
        onDelete={confirmDelete}
        itemCount={itemsToDelete.length}
        fileName={itemsToDelete.length === 1 ? itemsToDelete[0]?.name : undefined}
        error={deleteError}
        isLoading={isDeleting}
      />
    </section>
  );
}
