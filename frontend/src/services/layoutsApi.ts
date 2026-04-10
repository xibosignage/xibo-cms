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
import type { Template } from '@/types/templates';

export interface FetchLayoutRequest {
  start: number;
  length: number;
  keyword?: string;
  retired?: number | string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  folderId?: number;

  userId?: string;
  ownerUserGroupId?: string;
  lastModified?: string;
  activeDisplayGroupId?: number;
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
  name?: string;
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

export interface ExportLayoutRequest {
  includeData?: boolean;
  includeFallback?: boolean;
  fileName?: string;
}

export async function exportLayout(
  layoutId: number | string,
  payload: ExportLayoutRequest,
): Promise<Blob> {
  const params = new URLSearchParams();

  if (payload.includeData !== undefined) {
    params.append('includeData', payload.includeData ? '1' : '0');
  }

  if (payload.includeFallback !== undefined) {
    params.append('includeFallback', payload.includeFallback ? '1' : '0');
  }

  if (payload.fileName) {
    params.append('fileName', payload.fileName);
  }

  const response = await http.post(`/layout/export/${layoutId}`, params, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
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

export interface PublishLayoutRequest {
  publishNow?: number;
  publishDate?: string;
}

export async function publishLayout(
  layoutId: number | string,
  payload: PublishLayoutRequest,
): Promise<Layout> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.put(`/layout/publish/${layoutId}`, params, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return data;
}

export async function checkoutLayout(layoutId: number | string): Promise<Layout> {
  const { data } = await http.put(`/layout/checkout/${layoutId}`);
  return data;
}

export async function discardLayout(layoutId: number | string): Promise<Layout> {
  const { data } = await http.put(`/layout/discard/${layoutId}`);
  return data;
}

export async function assignLayoutToCampaign(campaignId: number, layoutId: number): Promise<void> {
  const params = new URLSearchParams();
  params.append('layoutId', String(layoutId));

  await http.post(`/campaign/layout/assign/${campaignId}`, params, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
}

export interface SaveAsTemplateRequest {
  name: string;
  includeWidgets: boolean;
  description?: string;
  tags?: string;
  folderId?: number;
}

export async function saveLayoutAsTemplate(
  layoutId: number | string,
  payload: SaveAsTemplateRequest,
): Promise<Template> {
  const params = new URLSearchParams();

  params.append('name', payload.name);
  params.append('includeWidgets', payload.includeWidgets ? '1' : '0');

  if (payload.description) {
    params.append('description', payload.description);
  }

  if (payload.tags) {
    params.append('tags', payload.tags);
  }

  if (payload.folderId !== undefined) {
    params.append('folderId', String(payload.folderId));
  }

  const { data } = await http.post(`/template/${layoutId}`, params, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  let result = data;

  if (typeof result === 'string') {
    const jsonStart = (result as string).indexOf('{');
    const jsonEnd = (result as string).lastIndexOf('}');

    if (jsonStart !== -1 && jsonEnd !== -1) {
      try {
        result = JSON.parse((result as string).slice(jsonStart, jsonEnd + 1));
      } catch {
        throw new Error('Failed to parse template response');
      }
    } else {
      throw new Error('Invalid response from save as template');
    }
  }

  return result;
}
