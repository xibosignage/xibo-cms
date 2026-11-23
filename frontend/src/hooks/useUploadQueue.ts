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

import axios, { AxiosError } from 'axios';
import { useCallback, useState, useEffect, useRef } from 'react';

import { uploadMedia, updateMedia, uploadMediaFromUrl, uploadThumbnail } from '@/services/mediaApi';

interface XiboApiErrorData {
  message?: string;
  result?: { message?: string };
}

interface XiboApiError {
  error: number;
  message: string;
  property?: string | null;
  help?: string | null;
}

export interface UploadItem {
  id: string;
  type: 'file' | 'url';
  file?: File;
  url?: string;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
  abortController?: AbortController;
  displayName?: string;
  tags?: string;
  isDirty?: boolean;
  mediaId?: number;
  folderId?: number;
  duration?: number;
  enableStat?: string;
  retired?: number;
  thumbnailBlob?: Blob;
  // The thumbnail the CMS last accepted for this item, so a retaken one is sent again.
  savedThumbnailBlob?: Blob;
  thumbnailFailed?: boolean;
}

/**
 * Whether the CMS has all of the item's bytes and is storing it, which can take minutes. URL items
 * are downloaded by the CMS, so they have no browser progress to show.
 */
export const isStoring = (item: UploadItem): boolean =>
  item.status === 'uploading' && (item.type === 'url' || item.progress >= 100);

// Whether the item needs the user's attention: its upload or its thumbnail failed.
export const hasFailed = (item: UploadItem): boolean =>
  item.status === 'error' || item.thumbnailFailed === true;

interface UseUploadQueueReturn {
  queue: UploadItem[];
  addFiles: (files: File[], folderId?: number) => void;
  removeFile: (id: string) => void;
  updateFileData: (
    id: string,
    data: { name?: string; tags?: string; thumbnailBlob?: Blob },
  ) => void;
  hasPendingUploads: boolean;
  saveMetadata: () => Promise<void>;
  clearQueue: () => void;
  clearFinished: () => void;
  addUrlToQueue: (url: string, folderId?: number) => void;
  // Counts the Add Media dialog's openings; items finished before the latest are from earlier ones.
  uploadSession: number;
  startUploadSession: () => void;
}

export const useUploadQueue = (defaultFolderId: number = 1): UseUploadQueueReturn => {
  const [queue, setQueue] = useState<UploadItem[]>([]);
  const [isUploading, setIsUploading] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [uploadSession, setUploadSession] = useState(0);

  const activeUploadId = useRef<string | null>(null);
  const sendingThumbnails = useRef(new Set<string>());

  const hasPendingUploads = queue.some(
    (item) => item.status === 'pending' || item.status === 'uploading',
  );

  const addFiles = (files: File[], folderId?: number) => {
    const newItems: UploadItem[] = files.map((file) => ({
      id: Math.random().toString(36).substring(2) + Date.now().toString(36),
      type: 'file',
      file,
      progress: 0,
      status: 'pending',
      displayName: file.name,
      tags: '',
      isDirty: false,
      folderId: folderId || defaultFolderId,
    }));
    setQueue((prev) => [...prev, ...newItems]);
  };

  const updateFileData = (
    id: string,
    data: { name?: string; tags?: string; thumbnailBlob?: Blob },
  ) => {
    setQueue((prev) =>
      prev.map((item) => {
        if (item.id !== id) {
          return item;
        }

        // If the initial upload failed (no mediaId) and the name changed, reset to pending so it retries
        const shouldRetry = item.status === 'error' && !item.mediaId && data.name !== undefined;

        return {
          ...item,
          displayName: data.name ?? item.displayName,
          tags: data.tags ?? item.tags,
          isDirty: true, // Marks item for sync if upload is done or in progress
          thumbnailBlob: data.thumbnailBlob !== undefined ? data.thumbnailBlob : item.thumbnailBlob,
          // A retake gets a fresh attempt, so the earlier failure no longer applies.
          ...(data.thumbnailBlob !== undefined && { thumbnailFailed: false }),
          ...(shouldRetry && { status: 'pending', error: undefined, progress: 0 }),
        };
      }),
    );
  };

  const removeFile = (id: string) => {
    setQueue((prev) => {
      const itemToRemove = prev.find((item) => item.id === id);
      if (itemToRemove?.status === 'uploading' && itemToRemove.abortController) {
        itemToRemove.abortController.abort();
      }
      return prev.filter((item) => item.id !== id);
    });

    if (activeUploadId.current === id) {
      activeUploadId.current = null;
      setIsUploading(false);
    }
  };

  const addUrlToQueue = (url: string, folderId?: number) => {
    const tempName = url.split('/').pop()?.split('?')[0] || 'remote-file';

    const newItem: UploadItem = {
      id: Math.random().toString(36).substring(2) + Date.now().toString(36),
      type: 'url',
      url: url,
      progress: 0,
      status: 'pending',
      displayName: tempName,
      tags: '',
      isDirty: false,
      folderId: folderId || defaultFolderId,
    };

    setQueue((prev) => [...prev, newItem]);
  };

  const clearQueue = () => {
    setQueue((prev) => {
      prev.forEach((item) => {
        if (item.status === 'uploading' && item.abortController) {
          item.abortController.abort();
        }
      });
      return [];
    });
    setIsUploading(false);
    activeUploadId.current = null;
  };

  // Keeps failed items, and finished items whose details or thumbnail are unsent or failed, so they can be retried.
  const clearFinished = useCallback(() => {
    setQueue((prev) =>
      prev.filter(
        (item) =>
          item.status !== 'completed' ||
          item.isDirty ||
          item.thumbnailFailed ||
          (item.thumbnailBlob !== undefined && item.thumbnailBlob !== item.savedThumbnailBlob),
      ),
    );
  }, []);

  const startUploadSession = useCallback(() => {
    setUploadSession((session) => session + 1);
  }, []);

  const saveMetadata = async () => {
    return Promise.resolve();
  };

  // Watches the queue for pending items, and then process them one by one
  useEffect(() => {
    // Prevents entering if already uploading
    if (isUploading || activeUploadId.current) {
      return;
    }

    const nextItem = queue.find((item) => item.status === 'pending');
    if (!nextItem) {
      return;
    }

    const uploadNext = async () => {
      setIsUploading(true);
      activeUploadId.current = nextItem.id;
      const controller = new AbortController();

      setQueue((prev) =>
        prev.map((item) =>
          item.id === nextItem.id
            ? { ...item, status: 'uploading', abortController: controller }
            : item,
        ),
      );

      try {
        const initialTags = nextItem.tags ? nextItem.tags.split(',').map((t) => t.trim()) : [];
        let newMediaId: number | undefined;
        let detectedDuration = 0;
        let detectedEnableStat = 'Inherit';
        let detectedRetired = 0;

        if (nextItem.type === 'url' && nextItem.url) {
          const response = await uploadMediaFromUrl({
            url: nextItem.url,
            name: nextItem.displayName,
            folderId: nextItem.folderId || defaultFolderId,
            tags: initialTags,
          });

          const result = response.data;
          if (result.error) {
            throw new Error(result.error);
          }
          if (!result.mediaId) {
            throw new Error('No Media ID returned');
          }

          newMediaId = result.mediaId;
          detectedDuration = result.duration || 10;
          detectedEnableStat = 'Inherit';
          detectedRetired = 0;
        } else if (nextItem.type === 'file' && nextItem.file) {
          const response = await uploadMedia({
            file: nextItem.file,
            name: nextItem.displayName,
            folderId: nextItem.folderId || defaultFolderId,
            tags: initialTags,
            signal: controller.signal,
            onProgress: (percent) => {
              setQueue((prev) =>
                prev.map((item) =>
                  item.id === nextItem.id ? { ...item, progress: percent } : item,
                ),
              );
            },
          });

          // A body that isn't JSON (for example a PHP warning printed before it) arrives as a string.
          if (typeof response.data !== 'object' || response.data === null) {
            throw new Error('The CMS returned an unexpected response. Check the CMS log.');
          }

          const fileResult = response.data.files?.[0];

          if (fileResult?.error) {
            throw new Error(fileResult.error);
          }

          if (!fileResult?.mediaId) {
            throw new Error('Upload successful, but no Media ID was returned.');
          }

          newMediaId = response.data.files?.[0]?.mediaId;
          detectedDuration = fileResult.duration ?? 0;
          detectedEnableStat = fileResult.enableStat ?? 'Inherit';
          detectedRetired = fileResult.retired ?? 0;
        }

        setQueue((prev) =>
          prev.map((item) =>
            item.id === nextItem.id
              ? {
                  ...item,
                  status: 'completed',
                  progress: 100,
                  mediaId: newMediaId,
                  duration: detectedDuration,
                  enableStat: detectedEnableStat,
                  retired: detectedRetired,
                }
              : item,
          ),
        );
      } catch (err: unknown) {
        if (!axios.isCancel(err)) {
          let errMsg = 'Upload failed';

          if (err instanceof AxiosError) {
            const data = err.response?.data as XiboApiErrorData | undefined;
            const apiError = err.response?.data as XiboApiError | undefined;

            if (apiError?.message) {
              errMsg = apiError.message;
            } else if (data?.message) {
              errMsg = data.message;
            } else {
              errMsg = err.message;
            }
          } else if (err instanceof Error) {
            errMsg = err.message;
          }

          setQueue((prev) =>
            prev.map((item) =>
              item.id === nextItem.id ? { ...item, status: 'error', error: errMsg } : item,
            ),
          );
        }
      } finally {
        activeUploadId.current = null;
        setIsUploading(false);
      }
    };

    void uploadNext();
  }, [queue, isUploading, defaultFolderId]);

  // Handles changing name and tag for a uploaded file ( or currently uploading )
  useEffect(() => {
    if (isSyncing) {
      return;
    }

    const itemToSync = queue.find(
      (item) =>
        (item.status === 'completed' || item.status === 'error') && item.isDirty && item.mediaId,
    );

    if (!itemToSync) {
      return;
    }

    const syncItem = async () => {
      setIsSyncing(true);
      try {
        await updateMedia(itemToSync.mediaId!, {
          name: itemToSync.displayName || 'Media',
          tags: itemToSync.tags,
          duration: itemToSync.duration ?? 10,
          enableStat: itemToSync.enableStat ?? 'Inherit',
          retired: itemToSync.retired ?? 0,
        });

        setQueue((prev) =>
          prev.map((item) =>
            item.id === itemToSync.id
              ? {
                  ...item,
                  isDirty: false,
                  status: 'completed',
                  error: undefined,
                }
              : item,
          ),
        );
      } catch (err: unknown) {
        let errorMsg = 'Failed to save changes';
        if (err instanceof AxiosError) {
          const data = err.response?.data as XiboApiErrorData | undefined;
          const apiError = err.response?.data as XiboApiError | undefined;

          if (apiError?.message) {
            errorMsg = apiError.message;
          } else if (data?.message) {
            errorMsg = data.message;
          }
        }

        setQueue((prev) =>
          prev.map((item) =>
            item.id === itemToSync.id
              ? {
                  ...item,
                  isDirty: false,
                  status: 'error',
                  error: errorMsg,
                }
              : item,
          ),
        );
      } finally {
        setIsSyncing(false);
      }
    };

    void syncItem();
  }, [queue, isSyncing]);

  // Sends a captured video thumbnail as soon as its media exists, independently of the upload dialog and of the
  // name/tag sync, so closing the dialog early or a failed sync doesn't lose it.
  useEffect(() => {
    const pending = queue.filter(
      (item) =>
        item.mediaId &&
        item.status !== 'uploading' &&
        item.thumbnailBlob &&
        item.thumbnailBlob !== item.savedThumbnailBlob &&
        !sendingThumbnails.current.has(item.id),
    );

    pending.forEach((item) => {
      const blob = item.thumbnailBlob!;
      sendingThumbnails.current.add(item.id);

      uploadThumbnail({ mediaId: item.mediaId!, image: blob })
        .then(() => {
          setQueue((prev) =>
            prev.map((i) =>
              i.id === item.id ? { ...i, savedThumbnailBlob: blob, thumbnailFailed: false } : i,
            ),
          );
        })
        .catch(() => {
          // Remembered as handled, so a failure isn't retried in a loop; retaking the thumbnail sends it again.
          setQueue((prev) =>
            prev.map((i) =>
              i.id === item.id ? { ...i, savedThumbnailBlob: blob, thumbnailFailed: true } : i,
            ),
          );
        })
        .finally(() => {
          sendingThumbnails.current.delete(item.id);
        });
    });
  }, [queue]);

  return {
    queue,
    addFiles,
    removeFile,
    updateFileData,
    hasPendingUploads,
    saveMetadata,
    clearQueue,
    clearFinished,
    addUrlToQueue,
    uploadSession,
    startUploadSession,
  };
};
