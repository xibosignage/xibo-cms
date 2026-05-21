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
import type {
  MediaDashboardData,
  PlaylistSpotsResponse,
  StatusDashboardData,
} from '@/types/dashboard';
import type { Media } from '@/types/media';
import type { Playlist } from '@/types/playlist';

export async function fetchStatusDashboard(signal?: AbortSignal): Promise<StatusDashboardData> {
  const response = await http.get<StatusDashboardData>('/statusdashboard', { signal });
  return response.data;
}

export async function fetchMediaDashboard(signal?: AbortSignal): Promise<MediaDashboardData> {
  const response = await http.get<MediaDashboardData>('/mediamanager', { signal });
  return response.data;
}

export interface FetchDashboardMediaResponse {
  rows: Media[];
  totalCount: number;
}

export async function fetchUnusedMedia(
  params: { start: number; length: number; sortBy?: string; sortDir?: string },
  signal?: AbortSignal,
): Promise<FetchDashboardMediaResponse> {
  const response = await http.get('/library', {
    params: { ...params, unusedOnly: 1 },
    signal,
  });
  const totalCount = parseInt(response.headers['x-total-count'] ?? '0', 10);
  return { rows: response.data, totalCount };
}

export async function fetchUnreleasedMedia(
  params: { start: number; length: number; sortBy?: string; sortDir?: string },
  signal?: AbortSignal,
): Promise<FetchDashboardMediaResponse> {
  const response = await http.get('/library', {
    params: { ...params, unreleasedOnly: 1 },
    signal,
  });
  const totalCount = parseInt(response.headers['x-total-count'] ?? '0', 10);
  return { rows: response.data, totalCount };
}

export interface FetchPlaylistsForDashboardResponse {
  rows: Playlist[];
  totalCount: number;
}

export async function fetchPlaylistsForDashboard(
  params: { start: number; length: number; name?: string },
  signal?: AbortSignal,
): Promise<FetchPlaylistsForDashboardResponse> {
  const response = await http.get('/playlistdashboard/data', { params, signal });
  const totalCount = parseInt(response.headers['x-total-count'] ?? '0', 10);
  return { rows: response.data, totalCount };
}

export async function fetchPlaylistSpots(
  playlistId: number,
  signal?: AbortSignal,
): Promise<PlaylistSpotsResponse> {
  const response = await http.get<PlaylistSpotsResponse>(`/playlistdashboard/${playlistId}`, {
    signal,
  });
  return response.data;
}

export interface UploadSpotMediaParams {
  file: File;
  playlistId: number;
  widgetId?: number;
  oldMediaId?: number;
  onProgress?: (progress: number) => void;
}

export interface UploadSpotMediaResponse {
  files: {
    mediaId: number;
    widgetId: number;
    name: string;
    size: number;
    mediaType: string;
    error?: string;
  }[];
}

export async function uploadSpotMedia({
  file,
  playlistId,
  widgetId,
  oldMediaId,
  onProgress,
}: UploadSpotMediaParams): Promise<UploadSpotMediaResponse> {
  const formData = new FormData();
  formData.append('files[]', file);
  formData.append('name[]', file.name);
  formData.append('playlistId', String(playlistId));
  formData.append('updateInLayouts', '1');
  formData.append('deleteOldRevisions', '1');

  if (widgetId != null) {
    formData.append('widgetId', String(widgetId));
  }
  if (oldMediaId != null) {
    formData.append('oldMediaId', String(oldMediaId));
  }

  const response = await http.post<UploadSpotMediaResponse>('/library', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
      'X-Requested-With': 'XMLHttpRequest',
    },
    onUploadProgress: (event) => {
      if (onProgress && event.total) {
        onProgress(Math.round((event.loaded * 100) / event.total));
      }
    },
  });

  return response.data;
}

export async function tidyLibrary(): Promise<{ countDeleted: number }> {
  const response = await http.delete<{ countDeleted: number }>('/library/tidy', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return response.data;
}

export async function deleteSpotWidget(widgetId: number, deleteMedia = false): Promise<void> {
  await http.delete(`/playlist/widget/${widgetId}`, {
    params: { deleteMedia: deleteMedia ? 1 : 0 },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
