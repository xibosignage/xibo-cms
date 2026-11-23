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
import type { Session } from '@/types/session';

export interface FetchSessionRequest {
  start: number;
  length: number;
  type?: string;
  fromDate?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchSessionResponse {
  rows: Session[];
  totalCount: number;
}

export async function fetchSession(
  options: FetchSessionRequest = { start: 0, length: 10 },
): Promise<FetchSessionResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/sessions', {
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

export async function logoutSession(userId: number | string): Promise<void> {
  await http.delete(`/sessions/logout/${userId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
