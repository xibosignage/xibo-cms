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
import { useEffect, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useTranslation } from 'react-i18next';

import {
  getMediaColumns,
  getBulkActions,
  FILTER_OPTIONS,
  INITIAL_FILTER_STATE,
  LIBRARY_TABS,
  type MediaFilterInput,
  ACCEPTED_MIME_TYPES,
} from './MediaConfig';
import MediaPreviewer from './components/MediaPreviewer';
import { UploadProgressDock } from './components/UploadProgressDock';
import { useMediaData } from './hooks/useMediaData';

import Button from '@/components/ui/Button';
import { FileUploader } from '@/components/ui/FileUploader';
import FilterInputs from '@/components/ui/FilterInputs';
import { FolderSelect } from '@/components/ui/FolderSelect_TOREMOVE';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUploadContext } from '@/context/UploadContext';
import { useDebounce } from '@/hooks/useDebounce';
import EditMediaModal from '@/pages/Library/Media/components/EditMediaModal';
import { deleteMedia, downloadMedia } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

export default function Media() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const [sorting, setSorting] = useState<SortingState>([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [previewItem, setPreviewItem] = useState<MediaRow | null>(null);
  const [filterInputs, setFilterInput] = useState<MediaFilterInput>(INITIAL_FILTER_STATE);

  const [isAddModalOpen, setAddModalOpen] = useState(false);
  const [selectedFolderId, setSelectedFolderId] = useState(1);

  // Will need to add more modals later
  const [openModal, setOpenModal] = useState({
    edit: false,
    share: false,
  });
  const [selectedMediaId, setSelectedMediaId] = useState<number | null>(null);

  const debouncedFilter = useDebounce(globalFilter, 500);

  const { queue, addFiles, removeFile, clearQueue, updateFileData, saveMetadata, addUrlToQueue } =
    useUploadContext();

  // Data fetching
  const {
    data: queryData,
    isLoading,
    isError,
    error: queryError,
    isPlaceholderData,
  } = useMediaData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
  });

  // Computed values
  const data = queryData?.rows;
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const [mediaList, setMediaList] = useState<MediaRow[]>([]);

  useEffect(() => {
    setMediaList(data ?? []);
  }, [data]);

  const selectedMedia = mediaList.find((m) => m.mediaId === selectedMediaId) ?? null;

  // Event handlers
  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['media'] });
  };

  // Handles dropping files anywhere on the screen
  const onGlobalDrop = (acceptedFiles: File[]) => {
    if (acceptedFiles.length > 0) {
      setAddModalOpen(true);
      addFiles(acceptedFiles, selectedFolderId);
      notify.success(t('Files added to queue'));
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
    addFiles(files, selectedFolderId);
  };

  const handleDelete = async (id: number) => {
    try {
      await deleteMedia(id);
      handleRefresh();
    } catch (err) {
      console.error('Failed to delete media!', err);
      alert(t('Failed to delete media!'));
    }
  };

  const handleBulkDelete = async (selectedItems: MediaRow[]) => {
    if (selectedItems.length === 0) {
      return;
    }

    const message = `${t('Are you sure you want to delete these items?')}\n(${selectedItems.length} selected)`;
    if (window.confirm(message)) {
      try {
        await Promise.all(selectedItems.map((item) => deleteMedia(item.mediaId)));
        setRowSelection({});
        handleRefresh();
      } catch (err) {
        console.error('Bulk delete error', err);
        alert(t('Some items could not be deleted. Check if they are in use.'));
        handleRefresh();
      }
    }
  };

  const handleDownload = async (row: MediaRow) => {
    try {
      await downloadMedia(row.mediaId, row.storedAs);
    } catch (error) {
      console.error('Download failed', error);
    }
  };

  const handlePreviewClick = (row: MediaRow) => {
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

  const openEditModal = (media: MediaRow) => {
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

  const columns = getMediaColumns({
    t,
    onPreview: handlePreviewClick,
    onDelete: handleDelete,
    onDownload: handleDownload,
    openEditModal,
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

  // TODO: Temporary placeholder folders
  const tempFolders = [
    { id: 1, name: 'Root Folder' },
    { id: 2, name: 'Marketing Assets' },
    { id: 3, name: 'Q1 Campaigns' },
    { id: 99, name: 'Temporary' },
  ];

  return (
    <section
      {...getGlobalRootProps()}
      className="space-y-4 flex-1 flex flex-col min-h-0 relative outline-none"
    >
      <input {...getGlobalInputProps()} />

      {isGlobalDragActive && !isAddModalOpen && (
        <div className="absolute bottom-14 left-1/2 -translate-x-1/2 z-50 justify-center flex flex-col items-center gap-3 mb-0">
          <span className="inline-flex justify-center items-center size-15.5 shadow-lg rounded-full border-7 animate-bounce border-blue-50 bg-xibo-blue-100 text-blue-800">
            <Upload className="shrink-0 size-6.5" />
          </span>
          <div className="bg-slate-50 border border-gray-200 px-4 py-2 shadow-lg rounded-full flex justify-center items-center gap-2">
            <span className="text-sm text-gray-800">{t('Upload files to ')}</span>
            <span className="text-xibo-blue-600 font-semibold flex">
              <div className="size-6.5 flex justify-center items-center">
                <Folder className="size-4"></Folder>
              </div>
              {`"${selectedFolderId === 1 ? 'Root Folder' : 'Selected Folder'}"`}
            </span>
          </div>
        </div>
      )}

      <div className="flex flex-row justify-between py-5 items-center gap-4">
        <TabNav activeTab="Media" navigation={LIBRARY_TABS} />
        <div className="flex items-center gap-2 md:mb-0">
          <Button variant="primary" onClick={() => setAddModalOpen(true)} leftIcon={Plus}>
            {t('Add Media')}
          </Button>
        </div>
      </div>

      <div className="flex flex-col md:flex-row justify-between items-center gap-4">
        <div className="w-full md:w-auto">
          <button className="py-2 px-3 w-full md:w-auto inline-flex justify-center md:justify-start items-center gap-x-2 border border-gray-200 bg-white text-gray-800 hover:bg-gray-50">
            <Folder className="w-4 h-4 text-gray-500 fill-gray-100" />
            {t('Folders')}
          </button>
        </div>

        <div className="flex items-center gap-2 md:w-auto w-full">
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
              className="py-2 px-3 pl-10 block h-[45px] bg-gray-100 rounded-lg md:w-[365px] w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none"
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
        options={FILTER_OPTIONS}
      />

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
          {error}
        </div>
      )}

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
        loading={isLoading && !isPlaceholderData}
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
            owner: false,
          },
        }}
        bulkActions={bulkActions}
      />

      <Modal
        isOpen={isAddModalOpen}
        onClose={handleCancelUpload}
        title={t('Add Media')}
        actions={addModalActions}
        size="lg"
      >
        <div className="flex flex-col gap-3">
          {/* TODO: Folder select hardcoded for now */}
          <FolderSelect
            value={selectedFolderId}
            folders={tempFolders}
            onChange={setSelectedFolderId}
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
            onUrlUpload={(url) => addUrlToQueue(url, selectedFolderId)}
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
          }}
          data={selectedMedia}
        />
      )}
      <UploadProgressDock isModalOpen={isAddModalOpen} />
    </section>
  );
}
