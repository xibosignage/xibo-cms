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
import type { Font, FontDetails } from '@/types/font';

export interface FetchFontsRequest {
  start: number;
  length: number;
  name?: string;
  id?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: number;
}

export interface FetchFontsResponse {
  rows: Font[];
  totalCount: number;
}

export async function fetchFonts(
  options: FetchFontsRequest = { start: 0, length: 10 },
): Promise<FetchFontsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/fonts', {
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

export async function uploadFont(
  file: File,
  onProgress?: (percent: number) => void,
): Promise<Font> {
  const formData = new FormData();
  formData.append('files', file);

  const response = await http.post('/fonts', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    onUploadProgress: (event) => {
      if (onProgress && event.total) {
        const percent = Math.round((event.loaded * 100) / event.total);
        onProgress(percent);
      }
    },
  });

  // The upload handler returns { files: [{ ...file, error?: string }] }
  // HTTP 200 is returned even on errors — the error is in the response body
  const uploaded = response.data?.files?.[0] ?? response.data;
  if (uploaded?.error) {
    throw new Error(uploaded.error);
  }

  return uploaded;
}

export async function fetchFontBlob(id: number): Promise<Blob> {
  const response = await http.get(`/fonts/download/${id}`, {
    responseType: 'blob',
  });
  return new Blob([response.data]);
}

export async function fetchFontDetails(id: number): Promise<FontDetails> {
  const response = await http.get(`/fonts/details/${id}`);
  return response.data.details;
}

export async function downloadFont(id: number, fileName: string): Promise<void> {
  const response = await http.get(`/fonts/download/${id}`, {
    responseType: 'blob',
  });

  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', fileName);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

export async function deleteFont(id: number): Promise<void> {
  await http.delete(`/fonts/${id}/delete`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
