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
import type { Layout } from '@/types/layout';

export interface FetchLayoutRequest {
  start: number;
  length: number;
  layout?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  folderId?: number;

  userId?: string;
  ownerUserGroupId?: string;
  lastModified?: string;
}

export interface FetchLayoutResponse {
  rows: Layout[];
  totalCount: number;
}

export async function fetchLayouts(
  options: FetchLayoutRequest = { start: 0, length: 10 },
): Promise<FetchLayoutResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/layout', {
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

export async function createLayout() {
  const response = await http.post(
    '/layout',
    new URLSearchParams({
      name: 'Untitled Layout',
      resolutionId: '1',
    }),
  );

  return response.data;
}

export interface UpdateLayoutRequest {
  name: string;
  description?: string | null;
  tags?: string;
  retired?: number;
  enableStat?: number;
  code?: string;
  folderId?: number;
}

export async function updateLayout(
  layoutId: number,
  payload: UpdateLayoutRequest,
): Promise<Layout> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.put(`/layout/${layoutId}`, params, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return data;
}

export async function deleteLayout(layoutId: number | string): Promise<void> {
  await http.delete(`/layout/${layoutId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export async function exportLayout(layoutId: number | string): Promise<Blob> {
  const response = await http.get(`/layout/export/${layoutId}`, {
    responseType: 'blob',
  });

  return response.data;
}

export interface CopyLayoutRequest {
  name: string;
  description?: string;
  copyMediaFiles: number;
}

export async function copyLayout(
  layoutId: number | string,
  payload: CopyLayoutRequest,
): Promise<Layout> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.post(`/layout/copy/${layoutId}`, params, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return data;
}
