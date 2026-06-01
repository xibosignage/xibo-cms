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
import type { Module } from '@/types/module';

export interface FetchModulesRequest {
  name?: string;
  keyword?: string;
  start?: number;
  length?: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchModulesResponse {
  rows: Module[];
  totalCount: number;
}

export async function fetchModules(
  options: FetchModulesRequest = {},
): Promise<FetchModulesResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/module', {
    params: queryParams,
    signal,
  });

  const rows: Module[] = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader
    ? parseInt(totalCountHeader, 10)
    : Array.isArray(rows)
      ? rows.length
      : 0;

  return { rows, totalCount };
}

export interface UpdateModuleSettingsRequest {
  enabled: number;
  previewEnabled: number;
  defaultDuration: number;
  [key: string]: string | number;
}

export async function updateModuleSettings(
  id: string,
  settings: UpdateModuleSettingsRequest,
): Promise<Module> {
  const params = new URLSearchParams();
  Object.entries(settings).forEach(([key, value]) => {
    params.append(key, String(value));
  });

  const response = await http.put(`/module/settings/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return response.data.data;
}

export async function clearModuleCache(id: string): Promise<void> {
  const params = new URLSearchParams();
  await http.put(`/module/clear-cache/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}
