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
import type { PlayerVersion } from '@/types/playerVersion';
import { navigateToDownload } from '@/utils/url';

export interface FetchPlayerVersionsRequest {
  start: number;
  length: number;
  playerShowVersion?: string;
  playerType?: string;
  playerVersion?: string;
  playerCode?: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface PlayerVersionMeta {
  types: { type: string; typeShow: string }[];
  versions: { version: string }[];
  validExt: string[];
}

export async function fetchPlayerVersionMeta(): Promise<PlayerVersionMeta> {
  const response = await http.get('/playersoftware/meta');
  return response.data;
}

export interface FetchPlayerVersionsResponse {
  rows: PlayerVersion[];
  totalCount: number;
}

export async function fetchPlayerVersions(
  options: FetchPlayerVersionsRequest = { start: 0, length: 10 },
): Promise<FetchPlayerVersionsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/playersoftware', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export async function fetchPlayerVersionById(versionId: number): Promise<PlayerVersion> {
  const response = await http.get(`/playersoftware/${versionId}`);
  return response.data;
}

export interface UpdatePlayerVersionRequest {
  version: string;
  code: number;
  playerShowVersion: string;
}

export async function updatePlayerVersion(
  versionId: number | string,
  data: UpdatePlayerVersionRequest,
): Promise<PlayerVersion> {
  const params = new URLSearchParams();
  params.append('version', data.version);
  params.append('code', String(data.code));
  params.append('playerShowVersion', data.playerShowVersion);

  const response = await http.put(`/playersoftware/${versionId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export function downloadPlayerVersion(versionId: number): void {
  navigateToDownload(withPublicPath(`json/playersoftware/download/${versionId}`));
}

export async function deletePlayerVersion(versionId: number | string): Promise<void> {
  await http.delete(`/playersoftware/${versionId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export interface UploadPlayerVersionResponse {
  id: number;
  md5: string;
  name: string;
}

export async function uploadPlayerVersion(
  file: File,
  onProgress?: (percent: number) => void,
): Promise<UploadPlayerVersionResponse> {
  const formData = new FormData();
  formData.append('files', file);

  const response = await http.post('/playersoftware', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      'X-Requested-With': 'XMLHttpRequest',
    },
    onUploadProgress: (progressEvent) => {
      if (onProgress && progressEvent.total) {
        const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        onProgress(percent);
      }
    },
  });

  // HTTP 200 is returned even on errors — the error is in the response body
  const uploaded = response.data?.files?.[0] ?? response.data;
  if (uploaded?.error) {
    throw new Error(uploaded.error);
  }

  return uploaded;
}
