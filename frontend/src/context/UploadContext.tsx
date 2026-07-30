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

import type { ReactNode } from 'react';
import { createContext, useContext } from 'react';

import { useUploadQueue, type UploadItem } from '@/hooks/useUploadQueue';

interface UploadContextType {
  queue: UploadItem[];
  addFiles: (files: File[], folderId?: number) => void;
  removeFile: (id: string) => void;
  updateFileData: (id: string, data: { name?: string; tags?: string }) => void;
  hasPendingUploads: boolean;
  saveMetadata: () => Promise<void>;
  clearQueue: () => void;
  addUrlToQueue: (url: string, folderId?: number) => void;
}

const UploadContext = createContext<UploadContextType | null>(null);

interface UploadProviderProps {
  children: ReactNode;
  defaultFolderId?: number;
}

export function UploadProvider({ children, defaultFolderId }: UploadProviderProps) {
  const uploadState = useUploadQueue(defaultFolderId);

  return <UploadContext.Provider value={uploadState}>{children}</UploadContext.Provider>;
}

export function useUploadContext() {
  const context = useContext(UploadContext);

  if (!context) {
    throw new Error('useUploadContext must be used within an UploadProvider');
  }

  return context;
}
