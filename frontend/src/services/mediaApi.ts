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

import http from '@/lib/api';
import type { MediaRow } from '@/types/media';

export interface FetchMediaRequest {
  start: number;
  length: number;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;

  type?: string;
  owner?: string;
  userGroup?: string;
  orientation?: string;
  retired?: string;
  lastModified?: string;
}

export interface FetchMediaResponse {
  rows: MediaRow[];
  totalCount: number;
}

export async function fetchMedia(
  options: FetchMediaRequest = { start: 0, length: 10 },
): Promise<FetchMediaResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/library', {
    params: queryParams,
    signal,
  });

  const rows = response.data;

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}

export interface UploadMediaRequest {
  file: File;
  folderId?: number | string;
  tags?: string[];
  onProgress?: (progress: number) => void;
  signal?: AbortSignal;
}

export interface UploadMediaResponse {
  files: {
    name: string;
    size: number;
    type: string;
    url?: string;
    mediaId: number;
    storedas: string;
    duration: number;
    retired: number;
    enableStat: string;
    mediaType: string;
    error?: string;
  }[];
}

export async function uploadMedia({
  file,
  folderId = 1,
  tags = [],
  onProgress,
  signal,
}: UploadMediaRequest): Promise<{ data: UploadMediaResponse }> {
  const formData = new FormData();

  formData.append('files[]', file);
  formData.append('name[]', file.name);

  if (folderId) {
    formData.append('folderId', folderId.toString());
  }

  if (tags.length > 0) {
    tags.forEach((tag) => formData.append('tags[]', tag));
  } else {
    formData.append('tags[]', '');
  }

  const response = await http.post<UploadMediaResponse>('/library', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      'X-Requested-With': 'XMLHttpRequest',
    },
    signal,
    onUploadProgress: (event) => {
      if (onProgress && event.total) {
        const percent = Math.round((event.loaded * 100) / event.total);
        onProgress(percent);
      }
    },
  });

  return { data: response.data };
}

export interface UploadUrlRequest {
  url: string;
  name?: string;
  folderId?: number | string;
  tags?: string[];
}

export interface UploadUrlResponse {
  mediaId: number;
  type: string;
  name: string;
  duration: number;
  fileSize: number;
  storedAs: string;
  error?: string;
}

export async function uploadMediaFromUrl({
  url,
  name,
  folderId = 1,
  tags = [],
}: UploadUrlRequest): Promise<{ data: UploadUrlResponse }> {
  const formData = new FormData();

  formData.append('url', url);

  if (name) formData.append('name', name);
  if (folderId) formData.append('folderId', folderId.toString());

  if (tags.length > 0) {
    tags.forEach((tag) => formData.append('tags[]', tag));
  } else {
    formData.append('tags[]', '');
  }

  const response = await http.post<UploadUrlResponse>('/library/uploadUrl', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return { data: response.data };
}

export interface UpdateMediaRequest {
  name: string;
  duration: number;
  tags?: string;
  retired?: number;
  enableStat?: string;
}

export async function updateMedia(
  mediaId: number | string,
  data: UpdateMediaRequest,
): Promise<void> {
  const params = new URLSearchParams();

  params.append('name', data.name);
  params.append('duration', data.duration.toString());
  params.append('retired', (data.retired ?? 0).toString());
  params.append('enableStat', data.enableStat ?? 'Inherit');

  if (data.tags !== undefined) {
    params.append('tags', data.tags);
  }

  await http.put(`/library/${mediaId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });
}

export async function fetchMediaBlob(mediaId: number | string): Promise<Blob> {
  const response = await http.get(`/library/download/${mediaId}`, {
    responseType: 'blob',
  });
  return response.data;
}

export async function downloadMedia(mediaId: number | string, fileName: string): Promise<void> {
  const blob = await fetchMediaBlob(mediaId);
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');

  link.href = url;
  link.setAttribute('download', fileName);

  document.body.appendChild(link); // Required for Firefox
  link.click();

  link.parentNode?.removeChild(link);
  window.URL.revokeObjectURL(url);
}

export async function deleteMedia(mediaId: number | string, force: boolean = false): Promise<void> {
  await http.delete(`/library/${mediaId}`, {
    params: { forceDelete: force ? 1 : 0 },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
