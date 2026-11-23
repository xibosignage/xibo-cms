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
import type { LogEntry } from '@/types/log';

export interface FetchLogsRequest {
  start: number;
  length: number;
  seconds?: number;
  intervalType?: number;
  fromDt?: string;
  level?: string;
  channel?: string;
  page?: string;
  function?: string;
  userId?: number;
  displayId?: number;
  excludeLog?: number;
  runNo?: string;
  message?: string;
  display?: string;
  useRegexForName?: number;
  displayGroupId?: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchLogsResponse {
  rows: LogEntry[];
  totalCount: number;
}

export async function fetchLogs(
  options: FetchLogsRequest = { start: 0, length: 10 },
): Promise<FetchLogsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/log', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export async function truncateLogs(): Promise<void> {
  await http.delete('/log');
}
