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

  return response.data;
}

export async function clearModuleCache(id: string): Promise<void> {
  const params = new URLSearchParams();
  await http.put(`/module/clear-cache/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}
