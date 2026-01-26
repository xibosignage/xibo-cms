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

import type { AxiosError } from 'axios';
import axios from 'axios';
import { useState, useEffect, useRef } from 'react';

import { uploadMedia } from '@/services/mediaApi';

interface XiboApiErrorData {
  message?: string;
  result?: {
    message?: string;
  };
}

export interface UploadItem {
  id: string;
  file: File;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
  abortController?: AbortController;
  displayName?: string;
  tags?: string;
}

interface UseUploadQueueReturn {
  queue: UploadItem[];
  addFiles: (files: File[]) => void;
  removeFile: (id: string) => void;
  updateFileData: (id: string, data: { name?: string; tags?: string }) => void;
  startUploads: () => void;
  isUploading: boolean;
}

export const useUploadQueue = (folderId: number = 1): UseUploadQueueReturn => {
  const [queue, setQueue] = useState<UploadItem[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [shouldStart, setShouldStart] = useState(false);
  const activeUploadId = useRef<string | null>(null);

  const addFiles = (files: File[]) => {
    const newItems: UploadItem[] = files.map((file) => ({
      id: Math.random().toString(36).substring(2) + Date.now().toString(36),
      file,
      progress: 0,
      status: 'pending',
      displayName: file.name, // Initialize with actual filename
      tags: '',
    }));
    setQueue((prev) => [...prev, ...newItems]);
  };

  const updateFileData = (id: string, data: { name?: string; tags?: string }) => {
    setQueue((prev) =>
      prev.map((item) =>
        item.id === id
          ? { ...item, displayName: data.name ?? item.displayName, tags: data.tags ?? item.tags }
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
      setIsProcessing(false);
    }
  };

  const startUploads = () => {
    setShouldStart(true);
  };

  useEffect(() => {
    if (!shouldStart || isProcessing || activeUploadId.current) return;

    const nextItem = queue.find((item) => item.status === 'pending');

    if (!nextItem) {
      setShouldStart(false);
      return;
    }

    const uploadNext = async () => {
      setIsProcessing(true);
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
        await uploadMedia({
          file: nextItem.file,
          folderId,
          tags: nextItem.tags ? nextItem.tags.split(',').map((t) => t.trim()) : [],
          signal: controller.signal,
          onProgress: (percent) => {
            setQueue((prev) =>
              prev.map((item) => (item.id === nextItem.id ? { ...item, progress: percent } : item)),
            );
          },
        });

        setQueue((prev) =>
          prev.map((item) =>
            item.id === nextItem.id ? { ...item, status: 'completed', progress: 100 } : item,
          ),
        );
      } catch (err) {
        if (!axios.isCancel(err)) {
          const error = err as AxiosError<XiboApiErrorData>;
          const errMsg =
            error.response?.data?.message ||
            error.response?.data?.result?.message ||
            error.message ||
            'Upload failed';

          setQueue((prev) =>
            prev.map((item) =>
              item.id === nextItem.id ? { ...item, status: 'error', error: errMsg } : item,
            ),
          );
        }
      } finally {
        activeUploadId.current = null;
        setIsProcessing(false);
      }
    };

    void uploadNext();
  }, [queue, isProcessing, shouldStart, folderId]);

  return { queue, addFiles, removeFile, updateFileData, startUploads, isUploading: isProcessing };
};
