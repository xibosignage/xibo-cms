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
.*/

import { useQueryClient } from '@tanstack/react-query';
import {
  type SortingState,
  type PaginationState,
  type OnChangeFn,
  type RowSelectionState,
} from '@tanstack/react-table';
import { Search, Filter, Folder, FilterX, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  getMediaColumns,
  getBulkActions,
  getAddModalActions,
  FILTER_OPTIONS,
  INITIAL_FILTER_STATE,
  LIBRARY_TABS,
  type MediaFilterInput,
} from './MediaConfig';
import MediaPreviewer from './components/MediaPreviewer';
import { useMediaData } from './hooks/useMediaData';

import Button from '@/components/ui/Button';
import { FileUploader } from '@/components/ui/FileUploader';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import Modal from '@/components/ui/modals/Modal';
import { DataTable } from '@/components/ui/table/DataTable';
import { useDebounce } from '@/hooks/useDebounce';
import { useUploadQueue } from '@/hooks/useUploadQueue';
import EditMediaModal from '@/pages/Library/Media/components/EditMediaModal';
import { deleteMedia, downloadMedia } from '@/services/mediaApi';
import type { MediaRow } from '@/types/media';

export default function Media() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  // UI state
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

  const debouncedFilter = useDebounce(globalFilter, 500);

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
  const data = queryData?.rows || [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';
  const [openModal, setOpenModal] = useState(false);
  const [selectedMedia, setSelectedMedia] = useState<MediaRow | null>();

  const { queue, addFiles, removeFile, updateFileData, startUploads, isUploading } =
    useUploadQueue(1);

  // Event handlers
  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['media'] });
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
    if (selectedItems.length === 0) return;

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

  const handleGlobalFilterChange = (value: string) => {
    setGlobalFilter(value);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const handleFilterChange = (name: string, value: string) => {
    setFilterInput((prev) => ({ ...prev, [name]: value }));
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const handlePaginationChange: OnChangeFn<PaginationState> = (updaterOrValue) => {
    setPagination((prev) => {
      const newPagination =
        typeof updaterOrValue === 'function' ? updaterOrValue(prev) : updaterOrValue;
      if (newPagination.pageSize !== prev.pageSize) {
        return { ...newPagination, pageIndex: 0 };
      }
      return newPagination;
    });
  };

  const handleOpenEditModal = (row: MediaRow) => {
    setSelectedMedia(row);
    setOpenModal(true);
  };

  const handleCloseModal = () => {
    setOpenModal(false);
    setSelectedMedia(null);
  };

  const columns = getMediaColumns({
    t,
    onPreview: handlePreviewClick,
    onDelete: handleDelete,
    onDownload: handleDownload,
    openEditModal: handleOpenEditModal,
  });

  const bulkActions = getBulkActions({
    t,
    onDelete: handleBulkDelete,
    onMove: (items) => console.log('Move', items),
    onShare: (items) => console.log('Share', items),
  });

  const addModalActions = getAddModalActions({
    t,
    onCancel: () => setAddModalOpen(false),
    onUpload: startUploads,
    isUploading,
    hasQueueItems: queue.length > 0,
  });

  // Side effects
  useEffect(() => {
    const allCompleted = queue.length > 0 && queue.every((item) => item.status === 'completed');
    if (allCompleted && !isUploading) {
      queryClient.invalidateQueries({ queryKey: ['media'] });
    }
  }, [queue, isUploading, queryClient]);

  // Render
  return (
    <section className="space-y-4 flex-1 flex flex-col min-h-0">
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
              onChange={(e) => handleGlobalFilterChange(e.target.value)}
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
        onChange={handleFilterChange}
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
        data={data}
        pageCount={pageCount}
        pagination={pagination}
        onPaginationChange={handlePaginationChange}
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
        closeOnOverlay={false}
        onClose={() => !isUploading && setAddModalOpen(false)}
        title={t('Add Media')}
        actions={addModalActions}
        size="lg"
      >
        <FileUploader
          queue={queue}
          addFiles={addFiles}
          removeFile={removeFile}
          updateFileData={updateFileData}
          isUploading={isUploading}
          maxSize={500 * 1024 * 1024}
          acceptedFileTypes={{
            'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.webp'],
            'video/*': ['.mp4', '.webm', '.mkv'],
            'audio/*': ['.mp3', '.wav', '.m4a'],
            'application/pdf': ['.pdf'],
            'application/zip': ['.zip'],
            'application/x-zip-compressed': ['.zip'],
          }}
        />
      </Modal>

      <MediaPreviewer
        mediaId={previewItem?.mediaId ?? null}
        mediaType={previewItem?.mediaType}
        fileName={previewItem?.name}
        mediaData={previewItem}
        onDownload={() => previewItem && handleDownload(previewItem)}
        onClose={() => setPreviewItem(null)}
      />

      {selectedMedia && (
        <EditMediaModal openModal={openModal} onClose={handleCloseModal} data={selectedMedia} />
      )}
    </section>
  );
}
