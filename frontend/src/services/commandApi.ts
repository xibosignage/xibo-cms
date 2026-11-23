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
import type { Command } from '@/types/command';

export interface FetchCommandsRequest {
  start: number;
  length: number;
  command?: string;
  code?: string;
  type?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  logicalOperatorName?: 'OR' | 'AND';
  useRegexForName?: number;
  logicalOperatorCode?: 'OR' | 'AND';
  useRegexForCode?: number;
}

export interface FetchCommandsResponse {
  rows: Command[];
  totalCount: number;
}

export async function fetchCommands(
  options: FetchCommandsRequest = { start: 0, length: 100 },
): Promise<FetchCommandsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/command', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export interface CreateCommandRequest {
  command: string;
  code: string;
  description?: string;
  commandString?: string;
  validationString?: string;
  availableOn?: string[];
  createAlertOn?: string;
}

function serializeCommandPayload(payload: object): URLSearchParams {
  const params = new URLSearchParams();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    if (key === 'availableOn' && Array.isArray(value)) {
      value.forEach((v) => params.append('availableOn[]', v));
    } else {
      params.append(key, String(value));
    }
  });
  return params;
}

export async function createCommand(payload: CreateCommandRequest): Promise<Command> {
  const { data } = await http.post('/command', serializeCommandPayload(payload), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export interface UpdateCommandRequest {
  command: string;
  description?: string;
  commandString?: string;
  validationString?: string;
  availableOn?: string[];
  createAlertOn?: string;
}

export async function updateCommand(
  commandId: number,
  payload: UpdateCommandRequest,
): Promise<Command> {
  const { data } = await http.put(`/command/${commandId}`, serializeCommandPayload(payload), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export async function deleteCommand(commandId: number): Promise<void> {
  await http.delete(`/command/${commandId}`);
}
