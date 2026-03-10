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

import { useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useTranslation } from 'react-i18next';

import { ACCEPTED_MIME_TYPES } from '../MediaConfig';

import { notify } from '@/components/ui/Notification';
import { useUploadContext } from '@/context/UploadContext';
import type { UploadItem } from '@/hooks/useUploadQueue';
import { uploadThumbnail } from '@/services/mediaApi';

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

  const { queue, addFiles, removeFile, clearQueue, updateFileData, saveMetadata, addUrlToQueue } =
    useUploadContext();

  const uploadStateRef = useRef({ targetId: targetUploadFolderId, canCreate: canAddToFolder });

  useEffect(() => {
    uploadStateRef.current = { targetId: targetUploadFolderId, canCreate: canAddToFolder };
  }, [targetUploadFolderId, canAddToFolder]);

  const onGlobalDrop = (acceptedFiles: File[]) => {
    const { targetId, canCreate } = uploadStateRef.current;

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

    const thumbnailPromises = queue
      .filter((item): item is UploadItem & { mediaId: number; thumbnailBlob: Blob } => {
        return !!item.thumbnailBlob && !!item.mediaId;
      })
      .map((item) => {
        return uploadThumbnail({
          mediaId: item.mediaId,
          image: item.thumbnailBlob,
        });
      });

    if (thumbnailPromises.length > 0) {
      try {
        await Promise.allSettled(thumbnailPromises);
      } catch (error) {
        console.error('Failed to save some thumbnails', error);
        notify.error(t('Some thumbnails failed to save.'));
      }
    }

    const hasPending = queue.some((item) => {
      return item.status === 'uploading' || item.status === 'pending';
    });

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
