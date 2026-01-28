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

import axios, { AxiosError } from 'axios';
import { useState, useEffect, useRef } from 'react';

import { uploadMedia, updateMedia, uploadMediaFromUrl } from '@/services/mediaApi';

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
  retryCount?: number;
}

interface UseUploadQueueReturn {
  queue: UploadItem[];
  addFiles: (files: File[], folderId?: number) => void;
  removeFile: (id: string) => void;
  updateFileData: (id: string, data: { name?: string; tags?: string }) => void;
  hasPendingUploads: boolean;
  saveMetadata: () => Promise<void>;
  clearQueue: () => void;
  addUrlToQueue: (url: string, folderId?: number) => void;
}

export const useUploadQueue = (defaultFolderId: number = 1): UseUploadQueueReturn => {
  const [queue, setQueue] = useState<UploadItem[]>([]);
  const [isUploading, setIsUploading] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);

  const activeUploadId = useRef<string | null>(null);

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

  const updateFileData = (id: string, data: { name?: string; tags?: string }) => {
    setQueue((prev) =>
      prev.map((item) =>
        item.id === id
          ? {
              ...item,
              displayName: data.name ?? item.displayName,
              tags: data.tags ?? item.tags,
              isDirty: true, // Marks item for sync if upload is done or in progress
              retryCount: 0,
            }
          : item,
      ),
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
        (item.status === 'completed' || item.status === 'error') &&
        item.isDirty &&
        item.mediaId &&
        (item.retryCount || 0) < 3,
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
                  retryCount: 0,
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
                  retryCount: (item.retryCount || 0) + 1,
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

  return {
    queue,
    addFiles,
    removeFile,
    updateFileData,
    hasPendingUploads,
    saveMetadata,
    clearQueue,
    addUrlToQueue,
  };
};
