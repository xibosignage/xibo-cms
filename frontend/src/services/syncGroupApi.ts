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

export interface SyncGroup {
  syncGroupId: number;
  name: string;
}

export interface SyncGroupDisplay {
  displayId: number;
  display: string;
  syncGroupId: number;
  leadDisplayId: number;
  displayGroupId: number;
  layoutId?: number | null;
}

export interface FetchSyncGroupsRequest {
  start: number;
  length: number;
  signal?: AbortSignal;
}

export interface FetchSyncGroupsResponse {
  rows: SyncGroup[];
  totalCount: number;
}

export async function fetchSyncGroups(
  options: FetchSyncGroupsRequest = { start: 0, length: 100 },
): Promise<FetchSyncGroupsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/syncgroups', {
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

export async function fetchSyncGroupDisplays(
  syncGroupId: number,
  eventId?: number,
): Promise<SyncGroupDisplay[]> {
  const response = await http.get(`/syncgroup/${syncGroupId}/displays`, {
    params: eventId ? { eventId } : undefined,
  });

  return response.data;
}
