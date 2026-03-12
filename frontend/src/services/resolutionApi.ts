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
import type { Resolution } from '@/types/resolution';

export interface FetchResolutionRequest {
  start: number;
  length: number;
  keyword?: string;
  enabled?: boolean;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchResolutionResponse {
  rows: Resolution[];
  totalCount: number;
}

export async function fetchResolution(
  options: FetchResolutionRequest = { start: 0, length: 10 },
): Promise<FetchResolutionResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/resolution', {
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

export interface UpdateResolutionRequest {
  resolution: string;
  width: number;
  height: number;
  enabled: boolean;
}

export async function createResolution(data: UpdateResolutionRequest): Promise<Resolution> {
  const params = new URLSearchParams();

  params.append('resolution', data.resolution);
  params.append('width', data.width.toString());
  params.append('height', data.height.toString());

  const response = await http.post(`/resolution`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function updateResolution(
  resolutionId: number | string,
  data: UpdateResolutionRequest,
): Promise<Resolution> {
  const params = new URLSearchParams();

  params.append('resolution', data.resolution);
  params.append('width', data.width.toString());
  params.append('height', data.height.toString());
  params.append('enabled', data.enabled ? '1' : '0');

  const response = await http.put(`/resolution/${resolutionId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteResolution(resolutionId: number | string): Promise<void> {
  await http.delete(`/resolution/${resolutionId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
