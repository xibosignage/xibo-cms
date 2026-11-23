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
import type { SyncGroup, SyncGroupDisplay } from '@/types/syncGroup';

export type { SyncGroup, SyncGroupDisplay };

export interface FetchSyncGroupsRequest {
  start: number;
  length: number;
  syncGroupId?: number | null;
  name?: string;
  leadDisplayId?: number | null;
  folderId?: number | null;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  useRegexForName?: number;
  logicalOperatorName?: 'OR' | 'AND';
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

  return response.data?.data?.displays ?? response.data?.displays ?? response.data;
}

export interface CreateSyncGroupRequest {
  name: string;
  syncPublisherPort?: number;
  syncSwitchDelay?: number;
  syncVideoPauseDelay?: number;
  folderId?: number;
}

export async function createSyncGroup(payload: CreateSyncGroupRequest): Promise<SyncGroup> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.post('/syncgroup/add', params, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export interface UpdateSyncGroupRequest {
  name: string;
  syncPublisherPort?: number;
  syncSwitchDelay?: number;
  syncVideoPauseDelay?: number;
  leadDisplayId?: number;
  folderId?: number;
}

export async function updateSyncGroup(
  syncGroupId: number,
  payload: UpdateSyncGroupRequest,
): Promise<SyncGroup> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.put(`/syncgroup/${syncGroupId}/edit`, params, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export async function deleteSyncGroup(syncGroupId: number): Promise<void> {
  await http.delete(`/syncgroup/${syncGroupId}/delete`);
}

export async function assignSyncGroupMembers(
  syncGroupId: number,
  displayIds: number[],
  unassignDisplayIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  displayIds.forEach((id) => params.append('displayId[]', String(id)));
  unassignDisplayIds.forEach((id) => params.append('unassignDisplayId[]', String(id)));

  await http.post(`/syncgroup/${syncGroupId}/members`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}
