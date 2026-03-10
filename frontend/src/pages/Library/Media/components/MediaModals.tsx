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

import { useTranslation } from 'react-i18next';

import { ACCEPTED_MIME_TYPES } from '../MediaConfig';

import CopyMediaModal from './CopyMediaModal';
import DeleteMediaModal from './DeleteMediaModal';
import EditMediaModal from './EditMediaModal';
import { MediaInfoPanel } from './MediaInfoPanel';
import ReplaceFileModal from './ReplaceFileModal';
import { UploadProgressDock } from './UploadProgressDock';

import { FileUploader } from '@/components/ui/FileUploader';
import FolderActionModals from '@/components/ui/FolderActionModals';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import Modal from '@/components/ui/modals/Modal';
import MoveModal from '@/components/ui/modals/MoveModal';
import ShareModal from '@/components/ui/modals/ShareModal';
import type { useFolderActions } from '@/hooks/useFolderActions';
import type { UploadItem } from '@/hooks/useUploadQueue';
import type { Media } from '@/types/media';
import type { Tag } from '@/types/tag';
import type { User } from '@/types/user';

interface MediaModalsProps {
  actions: {
    activeModal: string | null;
    closeModal: () => void;
    handleRefresh: () => void;
    setMediaList: React.Dispatch<React.SetStateAction<Media[]>>;
    deleteError: string | null;
    isDeleting: boolean;
    isCloning: boolean;
  };
  selection: {
    selectedMedia: Media | null;
    itemsToDelete: Media[];
    itemsToMove: Media[];
    existingNames: string[];
    shareEntityIds: number | number[] | null;
    setShareEntityIds: React.Dispatch<React.SetStateAction<number | number[] | null>>;
  };
  handlers: {
    confirmDelete: (options: { allLayouts: boolean; purgeList: boolean }) => void;
    handleConfirmClone: (newName: string, tags: Tag[]) => void;
    handleConfirmMove: (newFolderId: number) => void;
  };
  upload: {
    isOpen: boolean;
    setOpen: (open: boolean) => void;
    queue: UploadItem[];
    onStart: () => void;
    onCancel: () => void;
    onManualAdd: (files: File[]) => void;
    onUrlAdd: (url: string, folderId: number) => void;
    removeFile: (id: string) => void;
    updateFileData: (id: string, data: Partial<UploadItem>) => void;
    clearQueue: () => void;
    canAdd: boolean;
    targetFolderId: number;
    selectedFolderId: number | null;
    setSelectedFolderId: (id: number | null) => void;
    canViewFolders: boolean;
  };
  infoPanel: {
    isOpen: boolean;
    setOpen: (open: boolean) => void;
    setSelectedMediaId: (id: number | null) => void;
    owner: User | null;
    loading: boolean;
    folderName: string;
  };
  folderActions: ReturnType<typeof useFolderActions>;
}

export function MediaModals({
  actions,
  selection,
  handlers,
  upload,
  infoPanel,
  folderActions,
}: MediaModalsProps) {
  const { t } = useTranslation();

  const isModalOpen = (name: string) => actions.activeModal === name;

  const addModalActions = [
    {
      label: t('Cancel'),
      onClick: upload.onCancel,
      variant: 'secondary' as const,
      className: 'bg-transparent',
    },
    {
      label: t('Done'),
      onClick: upload.onStart,
      variant: 'primary' as const,
      disabled: upload.queue.length === 0,
    },
  ];

  return (
    <>
      <DeleteMediaModal
        isOpen={isModalOpen('delete')}
        onClose={actions.closeModal}
        onDelete={handlers.confirmDelete}
        itemCount={selection.itemsToDelete.length}
        fileName={
          selection.itemsToDelete.length === 1 ? selection.itemsToDelete[0]?.name : undefined
        }
        isLoading={actions.isDeleting}
        error={actions.deleteError}
      />

      <CopyMediaModal
        isOpen={isModalOpen('copy')}
        onClose={actions.closeModal}
        onConfirm={handlers.handleConfirmClone}
        media={selection.selectedMedia}
        isLoading={actions.isCloning}
        existingNames={selection.existingNames}
      />

      <MoveModal
        isOpen={isModalOpen('move')}
        onClose={actions.closeModal}
        onConfirm={handlers.handleConfirmMove}
        items={selection.itemsToMove}
        entityLabel="Media"
      />

      <ShareModal
        title={t('Share Media')}
        onClose={() => {
          actions.closeModal();
          selection.setShareEntityIds(null);
          actions.handleRefresh();
        }}
        openModal={isModalOpen('share')}
        entityType="media"
        entityId={selection.shareEntityIds ?? (selection.selectedMedia?.mediaId || null)}
      />

      {selection.selectedMedia && (
        <>
          {isModalOpen('edit') && (
            <EditMediaModal
              openModal={isModalOpen('edit')}
              onClose={actions.closeModal}
              onSave={(updatedMedia) => {
                actions.setMediaList((prev) =>
                  prev.map((m) => (m.mediaId === updatedMedia.mediaId ? updatedMedia : m)),
                );
                actions.handleRefresh();
              }}
              data={selection.selectedMedia}
            />
          )}

          {isModalOpen('replace') && (
            <ReplaceFileModal
              openModal={isModalOpen('replace')}
              onClose={actions.closeModal}
              data={selection.selectedMedia}
              onSave={(updatedMedia) => {
                actions.setMediaList((prev) =>
                  prev.map((m) => (m.mediaId === updatedMedia.mediaId ? updatedMedia : m)),
                );
                actions.handleRefresh();
              }}
            />
          )}
        </>
      )}

      <Modal
        isOpen={upload.isOpen}
        onClose={upload.onCancel}
        title={t('Add Media')}
        actions={addModalActions}
        size="lg"
      >
        <div className="flex flex-col gap-3 p-8 pt-0">
          {upload.canViewFolders && (
            <SelectFolder
              selectedId={upload.selectedFolderId}
              onSelect={(folder) => {
                if (folder) {
                  upload.setSelectedFolderId(folder.id);
                }
              }}
            />
          )}

          <FileUploader
            queue={upload.queue}
            acceptedFileTypes={ACCEPTED_MIME_TYPES}
            addFiles={upload.onManualAdd}
            removeFile={upload.removeFile}
            clearQueue={upload.clearQueue}
            updateFileData={upload.updateFileData}
            isUploading={false}
            maxSize={2 * 1024 * 1024 * 1024}
            disabled={!upload.canAdd}
            onUrlUpload={(url) => {
              upload.onUrlAdd(url, upload.targetFolderId);
            }}
          />
        </div>
      </Modal>

      <MediaInfoPanel
        open={infoPanel.isOpen}
        onClose={() => {
          infoPanel.setSelectedMediaId(null);
          infoPanel.setOpen(false);
        }}
        mediaData={selection.selectedMedia}
        owner={infoPanel.owner}
        applyVersionTwo
        folderName={infoPanel.folderName}
        loading={infoPanel.loading}
      />

      <UploadProgressDock isModalOpen={upload.isOpen} />

      <FolderActionModals folderActions={folderActions} />
    </>
  );
}
