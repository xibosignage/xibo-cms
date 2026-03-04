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
import type { Playlist } from '@/types/playlist';

export interface FetchPlaylistRequest {
  start: number;
  length: number;
  keyword?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  folderId?: number;

  userId?: string;
  ownerUserGroupId?: string;
  lastModified?: string;
}

export interface FetchPlaylistResponse {
  rows: Playlist[];
  totalCount: number;
}

export async function fetchPlaylist(
  options: FetchPlaylistRequest = { start: 0, length: 10 },
): Promise<FetchPlaylistResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/playlist', {
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

export interface CreatePlaylistRequest {
  name: string;
  folderId?: number | null;
  tags?: string;
  isDynamic?: boolean;
  enableStat?: string;
  filterMediaName?: string;
  logicalOperatorName?: 'OR' | 'AND';
  filterMediaTag?: string;
  exactTags?: boolean;
  logicalOperator?: 'OR' | 'AND';
  filterFolderId?: number | null;
  maxNumberOfItems?: number;
}

export async function createPlaylist(data: UpdatePlaylistRequest): Promise<Playlist> {
  const params = new URLSearchParams();

  params.append('name', data.name);
  params.append('enableStat', data.enableStat ?? 'Inherit');

  if (data.tags !== undefined) {
    params.append('tags', data.tags);
  }

  if (data.folderId) {
    params.append('folderId', data.folderId.toString());
  }

  if (data.isDynamic) {
    params.append('isDynamic', data.isDynamic.toString());

    if (data.filterMediaName) {
      params.append('filterMediaName', data.filterMediaName);
    }
    if (data.logicalOperatorName) {
      params.append('logicalOperatorName', data.logicalOperatorName);
    }
    if (data.filterMediaTag !== undefined) {
      params.append('filterMediaTag', data.filterMediaTag);
    }
    if (data.exactTags !== undefined) {
      params.append('exactTags', data.exactTags ? '1' : '0');
    }
    if (data.logicalOperator) {
      params.append('logicalOperator', data.logicalOperator);
    }
    if (data.filterFolderId) {
      params.append('filterFolderId', data.filterFolderId.toString());
    }
    if (data.maxNumberOfItems !== undefined) {
      params.append('maxNumberOfItems', data.maxNumberOfItems.toString());
    }
  }

  const response = await http.post(`/playlist`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface UpdatePlaylistRequest {
  name: string;
  folderId?: number | null;
  tags?: string;
  isDynamic?: boolean;
  enableStat?: string;
  filterMediaName?: string;
  logicalOperatorName?: 'OR' | 'AND';
  filterMediaTag?: string;
  exactTags?: boolean;
  logicalOperator?: 'OR' | 'AND';
  filterFolderId?: number | null;
  maxNumberOfItems?: number;
}

export async function updatePlaylist(
  playlistId: number | string,
  data: UpdatePlaylistRequest,
): Promise<Playlist> {
  const params = new URLSearchParams();

  params.append('name', data.name);
  params.append('enableStat', data.enableStat ?? 'Inherit');

  if (data.tags !== undefined) {
    params.append('tags', data.tags);
  }

  if (data.folderId) {
    params.append('folderId', data.folderId.toString());
  }

  if (data.isDynamic) {
    params.append('isDynamic', data.isDynamic.toString());

    if (data.filterMediaName) {
      params.append('filterMediaName', data.filterMediaName);
    }
    if (data.logicalOperatorName) {
      params.append('logicalOperatorName', data.logicalOperatorName);
    }
    if (data.filterMediaTag !== undefined) {
      params.append('filterMediaTag', data.filterMediaTag);
    }
    if (data.exactTags !== undefined) {
      params.append('exactTags', data.exactTags ? '1' : '0');
    }
    if (data.logicalOperator) {
      params.append('logicalOperator', data.logicalOperator);
    }
    if (data.filterFolderId) {
      params.append('filterFolderId', data.filterFolderId.toString());
    }
    if (data.maxNumberOfItems !== undefined) {
      params.append('maxNumberOfItems', data.maxNumberOfItems.toString());
    }
  }

  const response = await http.put(`/playlist/${playlistId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export interface ClonePlaylistRequest {
  playlistId: number | string;
  name: string;
  copyMediaFiles?: boolean;
  signal?: AbortSignal;
}

export async function clonePlaylist({
  playlistId,
  name,
  copyMediaFiles = false,
}: ClonePlaylistRequest): Promise<Playlist> {
  const params = new URLSearchParams();

  params.append('name', name);

  if (copyMediaFiles) {
    params.append('copyMediaFiles', '1');
  }

  const response = await http.post(`/playlist/copy/${playlistId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deletePlaylist(playlistId: number | string): Promise<void> {
  await http.delete(`/playlist/${playlistId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
