/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { useEffect, useRef, useState } from 'react';
import { useDropzone, type FileRejection } from 'react-dropzone';
import { useTranslation } from 'react-i18next';

import { ACCEPTED_MIME_TYPES } from '../MediaConfig';

import { notify } from '@/components/ui/Notification';
import { useUploadContext } from '@/context/UploadContext';
import type { UploadItem } from '@/hooks/useUploadQueue';

// Saves landing close together refresh the list once.
export const REFRESH_DELAY_MS = 1000;

interface UseMediaUploadProps {
  targetUploadFolderId: number;
  canAddToFolder: boolean;
  handleRefresh: () => void;
}

export function useMediaUpload({
  targetUploadFolderId,
  canAddToFolder,
  handleRefresh,
}: UseMediaUploadProps) {
  const { t } = useTranslation();
  const [isAddModalOpen, setAddModalOpen] = useState(false);

  const {
    queue,
    addFiles,
    removeFile,
    clearQueue,
    clearFinished,
    updateFileData,
    saveMetadata,
    addUrlToQueue,
    startUploadSession,
  } = useUploadContext();

  // Uploads finished since the dialog was last closed belong to that session, not this one.
  useEffect(() => {
    if (isAddModalOpen) {
      clearFinished();
      startUploadSession();
    }
  }, [isAddModalOpen, clearFinished, startUploadSession]);

  const handleRefreshRef = useRef(handleRefresh);
  const previousItemsRef = useRef(new Map<string, UploadItem>());
  const refreshTimerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => {
    handleRefreshRef.current = handleRefresh;
  }, [handleRefresh]);

  // Uploads, details and thumbnails keep saving after Done, so the list is refreshed once they settle.
  useEffect(() => {
    const previous = previousItemsRef.current;
    const hasSaved = queue.some((item) => {
      const before = previous.get(item.id);
      if (!before || item.status !== 'completed') {
        return false;
      }

      return (
        before.status !== 'completed' ||
        (before.isDirty === true && !item.isDirty) ||
        (!item.thumbnailFailed && item.savedThumbnailBlob !== before.savedThumbnailBlob)
      );
    });

    previousItemsRef.current = new Map(queue.map((item) => [item.id, item]));

    if (hasSaved) {
      clearTimeout(refreshTimerRef.current);
      refreshTimerRef.current = setTimeout(() => handleRefreshRef.current(), REFRESH_DELAY_MS);
    }
  }, [queue]);

  useEffect(() => () => clearTimeout(refreshTimerRef.current), []);

  const uploadStateRef = useRef({ targetId: targetUploadFolderId, canCreate: canAddToFolder });

  useEffect(() => {
    uploadStateRef.current = { targetId: targetUploadFolderId, canCreate: canAddToFolder };
  }, [targetUploadFolderId, canAddToFolder]);

  const onGlobalDrop = (acceptedFiles: File[], fileRejections: FileRejection[]) => {
    const { targetId, canCreate } = uploadStateRef.current;

    fileRejections.forEach((rejection) => {
      const isTooLarge = rejection.errors.some((error) => error.code === 'file-too-large');
      notify.error(
        isTooLarge
          ? t('{{name}} is too large to upload', { name: rejection.file.name })
          : t('{{name}} is not a supported file type', { name: rejection.file.name }),
      );
    });

    if (acceptedFiles.length > 0 && canCreate) {
      setAddModalOpen(true);
      addFiles(acceptedFiles, targetId);
      notify.success(t('Files added to queue'));
    } else if (!canCreate) {
      notify.error(t('You do not have permission to upload to this folder'));
    }
  };

  const dropzone = useDropzone({
    onDrop: onGlobalDrop,
    noClick: true,
    noKeyboard: true,
    accept: ACCEPTED_MIME_TYPES,
  });

  const handleManualAddFiles = (files: File[]) => {
    if (canAddToFolder) {
      addFiles(files, targetUploadFolderId);
    }
  };

  const handleStartUpload = async () => {
    await saveMetadata();

    // Video thumbnails are sent by the upload queue as soon as each upload finishes.
    const hasPending = queue.some((item) => {
      return item.status === 'uploading' || item.status === 'pending';
    });

    if (!hasPending) {
      // Keeps failed items, so they can be retried.
      clearFinished();
    }

    setAddModalOpen(false);
    handleRefresh();
  };

  const handleCancelUpload = () => {
    clearQueue();
    setAddModalOpen(false);
    handleRefresh();
  };

  return {
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
  };
}
