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

import { withPublicPath } from '@/config/publicPath';
import http from '@/lib/api';
import type { Font, FontDetails } from '@/types/font';
import { navigateToDownload } from '@/utils/url';

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

// Sent without credentials or the CSRF header, as a redirect target may allow any origin and
// the header would follow the redirect.
export async function fetchFontBlob(id: number): Promise<Blob> {
  const response = await http.get(`/fonts/download/${id}`, {
    responseType: 'blob',
    withCredentials: false,
    withXSRFToken: false,
  });
  return new Blob([response.data]);
}

export async function fetchFontDetails(id: number): Promise<FontDetails> {
  const response = await http.get(`/fonts/details/${id}`);
  return response.data.details;
}

export function downloadFont(id: number): void {
  navigateToDownload(withPublicPath(`json/fonts/download/${id}`));
}

export async function deleteFont(id: number): Promise<void> {
  await http.delete(`/fonts/${id}/delete`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
